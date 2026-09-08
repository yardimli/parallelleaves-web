<?php
namespace Tests\Feature;

use App\Models\User;
use App\Models\UserBook;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;
use ZipArchive;

class PublicFrontendTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default'=>'sqlite', 'database.connections.sqlite.database'=>':memory:', 'app.url'=>'https://paralleleaves.com']);
        Schema::create('users', function (Blueprint $t) {
            $t->id(); $t->string('username')->unique(); $t->string('email')->nullable()->unique();
            $t->string('password_hash')->nullable(); $t->rememberToken(); $t->string('session_token')->nullable();
            $t->timestamp('token_expires_at')->nullable(); $t->timestamp('created_at')->nullable();
            $t->string('google_id')->nullable(); $t->string('google_avatar')->nullable();
        });
        Schema::create('password_reset_tokens', function (Blueprint $t) { $t->string('email')->primary(); $t->string('token'); $t->timestamp('created_at')->nullable(); });
    }

    private function reader(): User
    {
        return User::create(['username'=>'reader','email'=>'reader@example.com','password_hash'=>Hash::make('old-password')]);
    }

    public function test_public_pages_render_and_prices_are_clear(): void
    {
        foreach (['/', '/subscription', '/terms', '/privacy', '/login', '/register', '/forgot-password', '/reset-password/example-token?email=reader@example.com'] as $url) {
            $this->get($url)->assertOk()->assertSee('Parallel Leaves');
        }
        $this->get('/subscription')->assertSee('$19')->assertSee('$199')->assertSee('USD')->assertSee('subscriptions are not active');
        $this->get('/')->assertSee('features/memory.png')->assertSee('features/rephrase.png')->assertSee('support@parallelleaves.com');
    }

    public function test_registration_stores_recovery_email_and_validates_duplicates(): void
    {
        $payload=['username'=>'new-reader','email'=>'new@example.com','password'=>'good-password'];
        $this->postJson('/api/auth/register',$payload)->assertOk()->assertJsonPath('success',true);
        $this->assertAuthenticated();
        $this->assertSame('new@example.com',User::first()->email);
        $this->assertTrue(Hash::check('good-password',User::first()->password_hash));
        $this->postJson('/api/auth/register',['args'=>[$payload]])->assertUnprocessable()->assertJsonValidationErrors(['username','email']);
        $this->postJson('/api/auth/register',['username'=>'another','password'=>'good-password'])->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    public function test_recovery_is_generic_throttled_and_sends_a_valid_single_use_token(): void
    {
        Notification::fake();
        $user=$this->reader();
        $this->post('/forgot-password',['email'=>$user->email])->assertSessionHas('status');
        $token=null;
        Notification::assertSentTo($user,ResetPassword::class,function ($notification) use (&$token,$user) {
            $token=$notification->token;
            $this->assertStringStartsWith('https://paralleleaves.com/reset-password/', $notification->toMail($user)->actionUrl);
            return true;
        });
        $this->assertNotSame($token,DB::table('password_reset_tokens')->value('token'));
        $this->post('/forgot-password',['email'=>$user->email])->assertSessionHas('status');
        Notification::assertSentToTimes($user,ResetPassword::class,1);
        $known=session('status');
        $this->post('/forgot-password',['email'=>'unknown@example.com'])->assertSessionHas('status',$known);
        $data=['email'=>$user->email,'token'=>$token,'password'=>'new-password','password_confirmation'=>'new-password'];
        $this->post('/reset-password',$data)->assertRedirect('/login')->assertSessionHas('status');
        $this->assertTrue(Hash::check('new-password',$user->fresh()->password_hash));
        $this->assertNull($user->fresh()->session_token);
        $this->post('/reset-password',$data)->assertSessionHasErrors('email');
        $this->postJson('/api/auth/login',['username'=>'reader','password'=>'old-password'])->assertJsonPath('success',false);
        $this->postJson('/api/auth/login',['username'=>'reader','password'=>'new-password'])->assertJsonPath('success',true);
    }

    public function test_bad_expired_and_mismatched_tokens_cannot_change_password(): void
    {
        $user=$this->reader();
        $token=Password::createToken($user);
        $data=['email'=>$user->email,'token'=>$token,'password'=>'new-password','password_confirmation'=>'different'];
        $this->post('/reset-password',$data)->assertSessionHasErrors('password');
        $data['password_confirmation']='new-password'; $data['token']='invalid';
        $this->post('/reset-password',$data)->assertSessionHasErrors('email');
        $data['token']=$token;
        $this->travel(61)->minutes();
        $this->post('/reset-password',$data)->assertSessionHasErrors('email');
        $this->assertTrue(Hash::check('old-password',$user->fresh()->password_hash));
    }

    public function test_recovery_requests_are_rate_limited_even_for_unknown_accounts(): void
    {
        Notification::fake();
        for ($i=0; $i<5; $i++) {
            $this->post('/forgot-password',['email'=>'unknown@example.com'])->assertRedirect();
        }
        $this->post('/forgot-password',['email'=>'unknown@example.com'])->assertStatus(429);
        Notification::assertNothingSent();
    }

    public function test_password_change_rejects_a_previously_authenticated_session(): void
    {
        $user=$this->reader();
        $previousHash=$user->password_hash;
        $user->forceFill(['password_hash'=>Hash::make('new-password')])->save();
        $this->actingAs($user)->withSession(['password_hash_web'=>$previousHash])->get('/dashboard')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_google_does_not_silently_link_an_unverified_password_signup(): void
    {
        $existing=$this->reader();
        $google=(new \Laravel\Socialite\Two\User())->map(['id'=>'google-123','name'=>'Reader','email'=>$existing->email,'avatar'=>null]);
        $provider=\Mockery::mock();
        $provider->shouldReceive('redirectUrl')->andReturnSelf();
        $provider->shouldReceive('user')->andReturn($google);
        \Laravel\Socialite\Facades\Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
        $this->get('/login/google/callback')->assertRedirect('/login')->assertSessionHas('google_error');
        $this->assertGuest();
        $this->assertNull($existing->fresh()->google_id);
        $this->assertTrue(Hash::check('old-password',$existing->fresh()->password_hash));
        // An already connected Google identity continues to sign in normally.
        $existing->forceFill(['google_id'=>'google-123'])->save();
        $this->get('/login/google/callback')->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($existing);
    }

    public function test_archive_is_owner_only_and_contains_all_memory_and_manuscript_data(): void
    {
        $owner=$this->reader();
        Schema::create('user_books',function(Blueprint $t){$t->id();$t->integer('user_id');$t->string('title');$t->text('codex_content')->nullable();$t->text('style_analysis_content')->nullable();$t->timestamps();});
        Schema::create('chapters',function(Blueprint $t){$t->id();$t->integer('book_id');$t->integer('chapter_order');$t->text('source_content');$t->text('target_content');});
        foreach(['user_book_dictionaries','user_books_translation_memory','translation_memory_blocks','user_book_blocks','user_book_codex_chunks','translation_logs'] as $table) {
            Schema::create($table,function(Blueprint $t){$t->id();$t->integer('book_id');$t->text('content');});
        }
        Schema::create('images',function(Blueprint $t){$t->id();$t->integer('book_id');$t->integer('user_id');$t->string('image_local_path')->nullable();$t->string('thumbnail_local_path')->nullable();});
        $book=UserBook::create(['user_id'=>$owner->id,'title'=>'Test Book','codex_content'=>'Character notes','style_analysis_content'=>'Style notes']);
        DB::table('chapters')->insert(['book_id'=>$book->id,'chapter_order'=>1,'source_content'=>'Original','target_content'=>'Translation']);
        for($i=0;$i<25;$i++) DB::table('user_books_translation_memory')->insert(['book_id'=>$book->id,'content'=>'memory '.$i]);
        DB::table('images')->insert(['book_id'=>$book->id,'user_id'=>$owner->id,'image_local_path'=>'../../../../../.env']);
        $this->get('/books/'.$book->id.'/archive')->assertRedirect('/login');
        $other=User::create(['username'=>'other','email'=>'other@example.com']);
        $this->actingAs($other)->get('/books/'.$book->id.'/archive')->assertNotFound();
        $this->app['session']->flush();
        $response=$this->actingAs($owner)->get('/books/'.$book->id.'/archive')->assertOk();
        $path=tempnam(sys_get_temp_dir(),'pl-test-');
        try {
            file_put_contents($path,$response->streamedContent());
            $zip=new ZipArchive(); $this->assertTrue($zip->open($path));
            $this->assertSame('Character notes',json_decode($zip->getFromName('book.json'),true)['codex_content']);
            $this->assertSame('Original',json_decode($zip->getFromName('chapters.json'),true)[0]['source_content']);
            $this->assertCount(25,json_decode($zip->getFromName('translation-memory.json'),true));
            $this->assertSame([],json_decode($zip->getFromName('covers.json'),true)[0]['archive_files']);
            $this->assertNotFalse($zip->getFromName('dictionary.json'));
            $this->assertNotFalse($zip->getFromName('README.txt'));
            $zip->close();
        } finally { unlink($path); }
    }
}

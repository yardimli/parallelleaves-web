<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PasswordLoginTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('username')->unique();
            $table->string('email')->nullable()->unique();
            $table->string('password_hash')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function test_password_login_accepts_username_and_email_in_rpc_and_flat_requests(): void
    {
        $user = User::create([
            'username' => 'reader',
            'email' => 'reader@example.com',
            'password_hash' => Hash::make('test-password'),
        ]);

        foreach (['reader', 'reader@example.com'] as $identifier) {
            foreach ([true, false] as $rpc) {
                $credentials = ['username' => $identifier, 'password' => 'test-password'];
                $this->postJson('/api/auth/login', $rpc ? ['args' => [$credentials]] : $credentials)
                    ->assertOk()
                    ->assertJsonPath('data.session.user.id', $user->id)
                    ->assertJsonMissingPath('data.session.user.password_hash');
                $this->assertAuthenticatedAs($user);

                // Resolve a fresh guard so this request must restore auth from the session.
                Auth::forgetGuards();
                $this->postJson('/api/auth/session')->assertJsonPath('data.user.id', $user->id);
                $this->postJson('/api/auth/logout')->assertOk();
            }
        }
    }

    public function test_invalid_passwords_unknown_users_and_accounts_without_passwords_are_rejected(): void
    {
        User::create(['username' => 'reader', 'email' => 'reader@example.com', 'password_hash' => Hash::make('test-password')]);
        User::create(['username' => 'google-reader', 'email' => 'google@example.com']);

        foreach (['reader', 'reader@example.com', 'missing@example.com', 'google@example.com'] as $identifier) {
            $this->postJson('/api/auth/login', ['args' => [[
                'username' => $identifier,
                'password' => 'incorrect-password',
            ]]])->assertOk()->assertJsonPath('success', false);
            $this->assertGuest();
        }
    }
}

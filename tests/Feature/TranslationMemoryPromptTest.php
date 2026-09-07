<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TranslationMemoryPromptTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        DB::statement('CREATE TABLE user_books (id INTEGER, user_id INTEGER, source_language TEXT, target_language TEXT)');
        DB::statement('CREATE TABLE user_books_translation_memory (id INTEGER, book_id INTEGER, source_sentence TEXT, original_target_sentence TEXT, edited_target_sentence TEXT)');
        // Emulate the production database's word-boundary REGEXP in isolated SQLite.
        DB::connection()->getPdo()->sqliteCreateFunction('regexp', function ($pattern, $value) {
            return preg_match('/' . str_replace(['[[:<:]]', '[[:>:]]'], '\\b', $pattern) . '/iu', $value);
        });
        DB::table('user_books')->insert([
            ['id' => 10, 'user_id' => 2, 'source_language' => 'English', 'target_language' => 'Turkish'],
            ['id' => 11, 'user_id' => 2, 'source_language' => 'English', 'target_language' => 'Turkish'],
            ['id' => 12, 'user_id' => 3, 'source_language' => 'English', 'target_language' => 'Turkish'],
        ]);
        foreach ([10, 11, 12] as $id) {
            DB::table('user_books_translation_memory')->insert([
                'id' => $id, 'book_id' => $id, 'source_sentence' => "Georgia memory $id",
                'original_target_sentence' => 'Unedited text',
                'edited_target_sentence' => "Edited translation $id",
            ]);
        }
        $user = new User();
        $user->id = 2;
        $this->actingAs($user);
    }

    public function test_preview_returns_saved_edited_memories_with_current_book_first(): void
    {
        // There is deliberately no chapters table: preview must never read chapters.
        $response = $this->postJson('/api/translation-memory/for-prompt', [
            'args' => [['bookId' => 10, 'text' => 'Georgia Georgia']],
        ]);
        $response->assertOk()->assertExactJson([
            'success' => true,
            'data' => "<English>Georgia memory 10</English>\n<Turkish>Edited translation 10</Turkish>\n<English>Georgia memory 11</English>\n<Turkish>Edited translation 11</Turkish>",
        ]);
    }

    public function test_no_matches_returns_an_empty_block(): void
    {
        $this->postJson('/api/translation-memory/for-prompt', [
            'args' => [['bookId' => 10, 'text' => 'Unmatched']],
        ])->assertOk()->assertJsonPath('data', '');
    }

    public function test_preview_rejects_another_users_book(): void
    {
        $this->postJson('/api/translation-memory/for-prompt', [
            'args' => [['bookId' => 12, 'text' => 'Georgia']],
        ])->assertNotFound();
    }

    public function test_preview_requires_authentication(): void
    {
        auth()->forgetGuards();
        $this->postJson('/api/translation-memory/for-prompt', [
            'args' => [['bookId' => 10, 'text' => 'Georgia']],
        ])->assertUnauthorized();
    }
}

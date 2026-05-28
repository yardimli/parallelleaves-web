<?php

use App\Http\Controllers\PageController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Api\AiApiController;
use App\Http\Controllers\Api\AppApiController;
use App\Http\Controllers\Api\BooksApiController;
use App\Http\Controllers\Api\ChaptersApiController;
use App\Http\Controllers\Api\ChatApiController;
use App\Http\Controllers\Api\CodexApiController;
use App\Http\Controllers\Api\DictionaryApiController;
use App\Http\Controllers\Api\DocumentsApiController;
use App\Http\Controllers\Api\I18nApiController;
use App\Http\Controllers\Api\LanguagesApiController;
use App\Http\Controllers\Api\LlmApiController;
use App\Http\Controllers\Api\LogApiController;
use App\Http\Controllers\Api\LogsApiController;
use App\Http\Controllers\Api\SessionApiController;
use App\Http\Controllers\Api\SplashApiController;
use App\Http\Controllers\Api\TemplatesApiController;
use App\Http\Controllers\Api\TmApiController;
use App\Http\Controllers\Api\TranslationMemoryApiController;
use App\Http\Controllers\Api\UserApiController;
use App\Http\Controllers\UploadController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect('/dashboard'));

Route::get('/login', [PageController::class, 'show'])->defaults('page', 'login')->name('login');
Route::get('/register', [PageController::class, 'show'])->defaults('page', 'register')->name('register');
Route::get('/splash', [PageController::class, 'show'])->defaults('page', 'splash');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [PageController::class, 'show'])->defaults('page', 'index');
    Route::get('/chapter-editor/{bookId}/{chapterId?}', [PageController::class, 'show'])->defaults('page', 'chapter-editor');
    Route::get('/chat/{bookId}', [PageController::class, 'show'])->defaults('page', 'chat-window');
    Route::get('/codex/{bookId?}', [PageController::class, 'show'])->defaults('page', 'codex-editor');
    Route::get('/editor-iframe', [PageController::class, 'show'])->defaults('page', 'editor-iframe');
    Route::get('/import-document', [PageController::class, 'show'])->defaults('page', 'import-document');
    Route::get('/api-logs', [PageController::class, 'show'])->defaults('page', 'api-logs');
    Route::get('/translation-memory/{bookId?}', [PageController::class, 'show'])->defaults('page', 'translation-memory');
});

Route::post('/api/auth/login', [AuthController::class, 'login']);
Route::post('/api/auth/register', [AuthController::class, 'register']);
Route::post('/api/auth/logout', [AuthController::class, 'logout']);
Route::post('/api/auth/session', [AuthController::class, 'session']);
Route::get('/login/google', [AuthController::class, 'redirectToGoogle'])->name('login.google');
Route::get('/login/google/callback', [AuthController::class, 'handleGoogleCallback'])->name('login.google.callback');

Route::post('/api/splash/init', [SplashApiController::class, 'init']);
Route::post('/api/i18n/lang-file', [I18nApiController::class, 'langFile']);

Route::middleware('auth')->group(function () {
    Route::post('/api/user/api-key', [UserApiController::class, 'setApiKey']);
    Route::post('/api/templates/get', [TemplatesApiController::class, 'get']);
    Route::post('/api/app/reset', [AppApiController::class, 'reset']);
    Route::post('/api/session/spellchecker/languages', [SessionApiController::class, 'availableSpellCheckerLanguages']);
    Route::post('/api/session/spellchecker/current', [SessionApiController::class, 'currentSpellCheckerLanguage']);
    Route::post('/api/session/spellchecker/set', [SessionApiController::class, 'setSpellCheckerLanguage']);
    Route::post('/api/logs', [LogsApiController::class, 'index']);
    Route::post('/api/languages/supported', [LanguagesApiController::class, 'supported']);
    Route::post('/api/books/with-covers', [BooksApiController::class, 'withCovers']);
    Route::post('/api/books/with-translation-memory', [BooksApiController::class, 'withTranslationMemory']);
    Route::post('/api/books/get', [BooksApiController::class, 'get']);
    Route::post('/api/books/full-manuscript', [BooksApiController::class, 'fullManuscript']);
    Route::post('/api/books/all-content', [BooksApiController::class, 'allContent']);
    Route::post('/api/books/for-export', [BooksApiController::class, 'forExport']);
    Route::post('/api/books/create-blank', [BooksApiController::class, 'createBlank']);
    Route::post('/api/books/update-meta', [BooksApiController::class, 'updateMeta']);
    Route::post('/api/books/update-prose-settings', [BooksApiController::class, 'updateProseSettings']);
    Route::post('/api/books/update-prompt-settings', [BooksApiController::class, 'updatePromptSettings']);
    Route::post('/api/books/update-cover', [BooksApiController::class, 'updateCover']);
    Route::post('/api/books/delete', [BooksApiController::class, 'delete']);
    Route::post('/api/books/export', [BooksApiController::class, 'export']);
    Route::post('/api/books/highest-marker', [BooksApiController::class, 'highestMarker']);
    Route::post('/api/chapters/update-field', [ChaptersApiController::class, 'updateField']);
    Route::post('/api/chapters/raw-content', [ChaptersApiController::class, 'rawContent']);
    Route::post('/api/chapters/rename', [ChaptersApiController::class, 'rename']);
    Route::post('/api/chapters/delete', [ChaptersApiController::class, 'delete']);
    Route::post('/api/chapters/insert', [ChaptersApiController::class, 'insert']);
    Route::post('/api/chapters/translation-context', [ChaptersApiController::class, 'translationContext']);
    Route::post('/api/documents/read', [DocumentsApiController::class, 'read']);
    Route::post('/api/documents/import', [DocumentsApiController::class, 'import']);
    Route::post('/api/llm/process-text', [LlmApiController::class, 'processText']);
    Route::post('/api/chat/send-message', [ChatApiController::class, 'sendMessage']);
    Route::post('/api/ai/models', [AiApiController::class, 'models']);
    Route::post('/api/ai/cover-prompt', [AiApiController::class, 'coverPrompt']);
    Route::post('/api/ai/generate-cover', [AiApiController::class, 'generateCover']);
    Route::post('/api/translation-log', [LogApiController::class, 'translation']);
    Route::post('/api/dictionary/get', [DictionaryApiController::class, 'get']);
    Route::post('/api/dictionary/save', [DictionaryApiController::class, 'save']);
    Route::post('/api/dictionary/for-ai', [DictionaryApiController::class, 'forAi']);
    Route::post('/api/translation-memory/books', [TmApiController::class, 'books']);
    Route::post('/api/translation-memory/details', [TmApiController::class, 'details']);
    Route::post('/api/translation-memory/delete', [TmApiController::class, 'delete']);
    Route::post('/api/translation-memory/start', [TranslationMemoryApiController::class, 'start']);
    Route::post('/api/translation-memory/process-batch', [TranslationMemoryApiController::class, 'processBatch']);
    Route::post('/api/codex/books', [CodexApiController::class, 'books']);
    Route::post('/api/codex/details', [CodexApiController::class, 'details']);
    Route::post('/api/codex/save', [CodexApiController::class, 'save']);
    Route::post('/api/codex/reset', [CodexApiController::class, 'reset']);
    Route::post('/api/codex/start', [CodexApiController::class, 'start']);
    Route::post('/api/codex/process-batch', [CodexApiController::class, 'processBatch']);
});
Route::post('/api/uploads', UploadController::class);

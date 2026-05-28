<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_books', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('user_id');
            $table->string('title');
            $table->string('author')->nullable();
            $table->string('status')->default('draft');
            $table->string('source_language', 100);
            $table->string('target_language', 100);
            $table->text('rephrase_settings')->nullable();
            $table->text('translate_settings')->nullable();
            $table->mediumText('codex_content')->nullable();
            $table->enum('codex_status', ['none', 'pending', 'generating', 'complete', 'error'])->default('none');
            $table->integer('codex_chunks_total')->default(0);
            $table->integer('codex_chunks_processed')->default(0);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
        });

        Schema::create('chapters', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('book_id');
            $table->string('title');
            $table->mediumText('source_content')->nullable();
            $table->mediumText('target_content')->nullable();
            $table->string('status', 50)->nullable();
            $table->integer('chapter_order');
            $table->timestamp('created_at')->nullable()->useCurrent();
            $table->timestamp('updated_at')->nullable()->useCurrent()->useCurrentOnUpdate();
        });

        Schema::create('images', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('user_id');
            $table->integer('book_id')->nullable();
            $table->string('image_local_path')->nullable();
            $table->string('thumbnail_local_path')->nullable();
            $table->string('remote_url', 500)->nullable();
            $table->text('prompt')->nullable();
            $table->string('image_type', 50)->nullable();
            $table->timestamp('created_at')->nullable()->useCurrent();
            $table->timestamp('updated_at')->nullable()->useCurrent()->useCurrentOnUpdate();
        });

        Schema::create('api_logs', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('user_id')->index();
            $table->string('action', 50);
            $table->longText('request_payload')->nullable();
            $table->longText('response_body')->nullable();
            $table->smallInteger('response_code');
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('translation_logs', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('user_id')->index();
            $table->integer('book_id');
            $table->integer('chapter_id');
            $table->text('source_text');
            $table->text('target_text');
            $table->string('marker')->nullable();
            $table->string('model');
            $table->float('temperature');
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('tm_generation_jobs', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('book_id')->index('user_book_id');
            $table->enum('status', ['pending', 'running', 'complete', 'error'])->default('pending');
            $table->integer('total_blocks')->default(0);
            $table->integer('processed_blocks')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
        });

        Schema::create('tm_jobs', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('book_id');
            $table->enum('status', ['pending', 'running', 'complete', 'error'])->default('pending');
            $table->integer('total_blocks')->default(0);
            $table->integer('processed_blocks')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamp('created_at')->nullable()->useCurrent();
            $table->timestamp('updated_at')->nullable()->useCurrent()->useCurrentOnUpdate();
        });

        Schema::create('translation_memory_blocks', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('book_id');
            $table->integer('marker_id');
            $table->text('source_text');
            $table->text('target_text');
            $table->boolean('is_analyzed')->default(false);
            $table->unique(['book_id', 'marker_id'], 'novel_marker');
        });

        Schema::create('user_book_blocks', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('book_id')->index('user_book_id');
            $table->integer('marker_id');
            $table->text('source_text');
            $table->text('target_text');
            $table->boolean('is_analyzed')->default(false);
            $table->unique(['book_id', 'marker_id'], 'user_book_id_marker_id');
        });

        Schema::create('user_books_translation_memory', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('book_id')->index('user_book_id');
            $table->integer('block_id')->index();
            $table->text('source_sentence');
            $table->text('target_sentence');
            $table->fullText('source_sentence');
        });

        Schema::create('user_book_codex_chunks', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('book_id')->index('user_book_id');
            $table->integer('chunk_index');
            $table->mediumText('chunk_text');
            $table->boolean('is_processed')->default(false);
            $table->unique(['book_id', 'chunk_index'], 'user_book_id_chunk_index');
        });
    }

    public function down(): void
    {
        foreach ([
            'user_book_codex_chunks',
            'user_books_translation_memory',
            'user_book_blocks',
            'translation_memory_blocks',
            'tm_jobs',
            'tm_generation_jobs',
            'translation_logs',
            'api_logs',
            'images',
            'chapters',
            'user_books',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};

<?php

	use Illuminate\Database\Migrations\Migration;
	use Illuminate\Database\Schema\Blueprint;
	use Illuminate\Support\Facades\DB;
	use Illuminate\Support\Facades\Schema;

	return new class extends Migration {
		public function up(): void
		{
			if (!Schema::hasColumn('user_books_translation_memory', 'target_sentence')) {
				return;
			}

			if (Schema::hasColumn('user_books_translation_memory', 'original_target_sentence')) {
				DB::table('user_books_translation_memory')
					->whereNull('original_target_sentence')
					->update(['original_target_sentence' => DB::raw('target_sentence')]);
			}

			if (Schema::hasColumn('user_books_translation_memory', 'edited_target_sentence')) {
				DB::table('user_books_translation_memory')
					->whereNull('edited_target_sentence')
					->update(['edited_target_sentence' => DB::raw('target_sentence')]);
			}

			Schema::table('user_books_translation_memory', function (Blueprint $table) {
				$table->dropColumn('target_sentence');
			});
		}

		public function down(): void
		{
			if (Schema::hasColumn('user_books_translation_memory', 'target_sentence')) {
				return;
			}

			Schema::table('user_books_translation_memory', function (Blueprint $table) {
				$table->text('target_sentence')->nullable()->after('source_sentence');
			});

			if (Schema::hasColumn('user_books_translation_memory', 'edited_target_sentence')) {
				DB::table('user_books_translation_memory')
					->whereNull('target_sentence')
					->update(['target_sentence' => DB::raw('edited_target_sentence')]);
			}
		}
	};

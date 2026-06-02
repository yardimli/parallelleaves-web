<?php

	use Illuminate\Database\Migrations\Migration;
	use Illuminate\Database\Schema\Blueprint;
	use Illuminate\Support\Facades\Schema;

	return new class extends Migration {
		public function up(): void
		{
			if (!Schema::hasColumn('user_book_blocks', 'machine_target_text')) {
				Schema::table('user_book_blocks', function (Blueprint $table) {
					$table->text('machine_target_text')->nullable()->after('target_text');
				});
			}
		}

		public function down(): void
		{
			if (Schema::hasColumn('user_book_blocks', 'machine_target_text')) {
				Schema::table('user_book_blocks', function (Blueprint $table) {
					$table->dropColumn('machine_target_text');
				});
			}
		}
	};

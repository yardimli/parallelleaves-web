<?php

	use Illuminate\Database\Migrations\Migration;
	use Illuminate\Database\Schema\Blueprint;
	use Illuminate\Support\Facades\Schema;

	return new class extends Migration {
		public function up(): void
		{
			Schema::table('user_books', function (Blueprint $table) {
				$table->mediumText('style_analysis_content')->nullable()->after('codex_chunks_processed');
				$table->string('style_analysis_status', 50)->default('none')->after('style_analysis_content');
				$table->integer('style_analysis_percent')->default(5)->after('style_analysis_status');
				$table->integer('style_analysis_chunks_total')->default(0)->after('style_analysis_percent');
				$table->integer('style_analysis_chunks_processed')->default(0)->after('style_analysis_chunks_total');
			});
		}

		public function down(): void
		{
			Schema::table('user_books', function (Blueprint $table) {
				$table->dropColumn([
					'style_analysis_content',
					'style_analysis_status',
					'style_analysis_percent',
					'style_analysis_chunks_total',
					'style_analysis_chunks_processed',
				]);
			});
		}
	};

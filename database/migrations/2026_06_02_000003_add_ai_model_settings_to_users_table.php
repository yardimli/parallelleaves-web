<?php

	use Illuminate\Database\Migrations\Migration;
	use Illuminate\Database\Schema\Blueprint;
	use Illuminate\Support\Facades\Schema;

	return new class extends Migration {
		public function up(): void
		{
			if (!Schema::hasColumn('users', 'ai_model_settings')) {
				Schema::table('users', function (Blueprint $table) {
					$table->json('ai_model_settings')->nullable()->after('openrouter_api_key');
				});
			}
		}

		public function down(): void
		{
			if (Schema::hasColumn('users', 'ai_model_settings')) {
				Schema::table('users', function (Blueprint $table) {
					$table->dropColumn('ai_model_settings');
				});
			}
		}
	};

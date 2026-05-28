<?php

	use Illuminate\Database\Migrations\Migration;
	use Illuminate\Support\Facades\DB;
	use Illuminate\Support\Facades\Schema;

	return new class extends Migration {
		public function up(): void
		{
			if (!Schema::hasColumn('users', 'email')) {
				DB::statement('ALTER TABLE users ADD email VARCHAR(255) NULL UNIQUE AFTER username');
			}

			if (!Schema::hasColumn('users', 'google_id')) {
				DB::statement('ALTER TABLE users ADD google_id VARCHAR(255) NULL UNIQUE AFTER email');
			}

			if (!Schema::hasColumn('users', 'google_avatar')) {
				DB::statement('ALTER TABLE users ADD google_avatar VARCHAR(1024) NULL AFTER google_id');
			}

			DB::statement('ALTER TABLE users MODIFY password_hash VARCHAR(255) NULL');
		}

		public function down(): void
		{
			DB::statement("UPDATE users SET password_hash = '' WHERE password_hash IS NULL");
			DB::statement('ALTER TABLE users MODIFY password_hash VARCHAR(255) NOT NULL');

			if (Schema::hasColumn('users', 'google_avatar')) {
				DB::statement('ALTER TABLE users DROP COLUMN google_avatar');
			}

			if (Schema::hasColumn('users', 'google_id')) {
				DB::statement('ALTER TABLE users DROP INDEX users_google_id_unique');
				DB::statement('ALTER TABLE users DROP COLUMN google_id');
			}

			if (Schema::hasColumn('users', 'email')) {
				DB::statement('ALTER TABLE users DROP INDEX users_email_unique');
				DB::statement('ALTER TABLE users DROP COLUMN email');
			}
		}
	};

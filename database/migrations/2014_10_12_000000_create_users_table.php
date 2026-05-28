<?php

	use Illuminate\Database\Migrations\Migration;
	use Illuminate\Database\Schema\Blueprint;
	use Illuminate\Support\Facades\Schema;

	return new class extends Migration {
		/**
		 * Run the migrations.
		 */
		public function up(): void
		{
			Schema::create('users', function (Blueprint $table) {
				$table->increments('id');
				$table->string('username', 50)->unique();
				$table->string('password_hash');
				$table->string('openrouter_api_key')->nullable();
				$table->string('session_token', 64)->nullable();
				$table->dateTime('token_expires_at')->nullable();
				$table->timestamp('created_at')->useCurrent();
				$table->rememberToken();
			});
		}

		/**
		 * Reverse the migrations.
		 */
		public function down(): void
		{
			Schema::dropIfExists('users');
		}
	};

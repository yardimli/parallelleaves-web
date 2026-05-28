<?php

	use Illuminate\Database\Migrations\Migration;
	use Illuminate\Database\Schema\Blueprint;
	use Illuminate\Support\Facades\Schema;

	return new class extends Migration
	{
		/**
		 * Run the migrations to create the dictionary table.
		 */
		public function up(): void
		{
			// NEW: Created user_book_dictionaries table to replace flat JSON files [1]
			Schema::create('user_book_dictionaries', function (Blueprint $table) {
				$table->id();
				$table->unsignedBigInteger('book_id');
				$table->string('source', 255)->nullable();
				$table->string('target', 255)->nullable();
				$table->string('type', 50)->default('translation');
				$table->timestamps();

				// Set cascade delete to maintain referential integrity when a book is deleted
				$table->foreign('book_id')
					->references('id')
					->on('user_books')
					->onDelete('cascade');
			});
		}

		/**
		 * Reverse the migrations.
		 */
		public function down(): void
		{
			Schema::dropIfExists('user_book_dictionaries');
		}
	};

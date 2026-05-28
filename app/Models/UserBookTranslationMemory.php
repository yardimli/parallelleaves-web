<?php

	namespace App\Models;

	use Illuminate\Database\Eloquent\Model;

	class UserBookTranslationMemory extends Model
	{
		public $timestamps = false;

		protected $table = 'user_books_translation_memory';

		protected $fillable = ['book_id', 'block_id', 'source_sentence', 'target_sentence'];
	}

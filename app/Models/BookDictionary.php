<?php

	namespace App\Models;

	use Illuminate\Database\Eloquent\Model;

	class BookDictionary extends Model
	{
		// NEW: Eloquent model to interact with the dictionaries database table [1]
		protected $table = 'user_book_dictionaries';

		protected $fillable = [
			'book_id',
			'source',
			'target',
			'type',
		];

		/**
		 * Get the book that owns this dictionary entry.
		 */
		public function book()
		{
			return $this->belongsTo(UserBook::class, 'book_id');
		}
	}

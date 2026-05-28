<?php

	namespace App\Models;

	use Illuminate\Database\Eloquent\Model;

	class TranslationLog extends Model
	{
		public const UPDATED_AT = null;

		protected $fillable = [
			'user_id',
			'book_id',
			'chapter_id',
			'source_text',
			'target_text',
			'marker',
			'model',
			'temperature',
		];
	}

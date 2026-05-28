<?php

	namespace App\Models;

	use Illuminate\Database\Eloquent\Model;

	class UserBook extends Model
	{
		protected $table = 'user_books';

		protected $fillable = [
			'user_id',
			'title',
			'author',
			'status',
			'source_language',
			'target_language',
			'rephrase_settings',
			'translate_settings',
			'codex_content',
			'codex_status',
			'codex_chunks_total',
			'codex_chunks_processed',
		];

		public function user()
		{
			return $this->belongsTo(User::class);
		}

		public function chapters()
		{
			return $this->hasMany(Chapter::class, 'book_id')->orderBy('chapter_order');
		}

		public function images()
		{
			return $this->hasMany(Image::class, 'book_id');
		}
	}

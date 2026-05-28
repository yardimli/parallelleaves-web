<?php

	namespace App\Models;

	use Illuminate\Database\Eloquent\Model;

	class Image extends Model
	{
		protected $fillable = [
			'user_id',
			'book_id',
			'image_local_path',
			'thumbnail_local_path',
			'remote_url',
			'prompt',
			'image_type',
		];
	}

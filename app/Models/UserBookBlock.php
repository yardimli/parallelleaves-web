<?php

	namespace App\Models;

	use Illuminate\Database\Eloquent\Model;

	class UserBookBlock extends Model
	{
		public $timestamps = false;

		protected $fillable = ['book_id', 'marker_id', 'source_text', 'target_text', 'is_analyzed'];
	}

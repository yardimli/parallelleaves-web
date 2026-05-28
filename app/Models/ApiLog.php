<?php

	namespace App\Models;

	use Illuminate\Database\Eloquent\Model;

	class ApiLog extends Model
	{
		public const UPDATED_AT = null;

		protected $fillable = ['user_id', 'action', 'request_payload', 'response_body', 'response_code'];
	}

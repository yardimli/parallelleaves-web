<?php

	namespace App\Models;

	use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
	use Illuminate\Database\Eloquent\Factories\HasFactory;
	use Illuminate\Database\Eloquent\Model;
	use Illuminate\Auth\Authenticatable;

	class User extends Model implements AuthenticatableContract
	{
		use Authenticatable;
		use HasFactory;

		public const UPDATED_AT = null;

		protected $fillable = [
			'username',
			'email',
			'google_id',
			'google_avatar',
			'password_hash',
			'is_admin',
			'openrouter_api_key',
			'ai_model_settings',
			'session_token',
			'token_expires_at',
		];

		protected $hidden = [
			'password_hash',
			'remember_token',
			'session_token',
		];

		protected $casts = [
			'token_expires_at' => 'datetime',
			'ai_model_settings' => 'array',
			'is_admin' => 'boolean',
		];

		public function getAuthPassword(): string
		{
			return (string)$this->password_hash;
		}

		public function books()
		{
			return $this->hasMany(UserBook::class);
		}
	}

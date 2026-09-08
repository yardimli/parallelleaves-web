<?php

	namespace App\Providers;

	use Illuminate\Support\ServiceProvider;

	class AppServiceProvider extends ServiceProvider
	{
		/**
		 * Register any application services.
		 */
		public function register(): void
		{
			//
		}

		/**
		 * Bootstrap any application services.
		 */
		public function boot(): void
		{
			\Illuminate\Auth\Notifications\ResetPassword::createUrlUsing(function ($user, string $token) {
				return rtrim(config('app.url'), '/') . '/reset-password/' . $token . '?' . http_build_query(['email' => $user->getEmailForPasswordReset()]);
			});
		}
	}

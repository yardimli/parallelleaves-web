<?php

	namespace App\Http\Controllers;

	use App\Models\User;
	use Illuminate\Http\JsonResponse;
	use Illuminate\Http\RedirectResponse;
	use Illuminate\Http\Request;
	use Illuminate\Support\Facades\Auth;
	use Illuminate\Support\Facades\Hash;
	use Illuminate\Support\Str;
	use Laravel\Socialite\Facades\Socialite;
	use Throwable;

	class AuthController extends Controller
	{
		private function rpcCredentials(Request $request): array
		{
			$args = $request->input('args', []);
			$firstArg = is_array($args) ? ($args[0] ?? []) : [];

			return is_array($firstArg) && $firstArg
				? $firstArg
				: $request->only(['username', 'password']);
		}

		public function login(Request $request): JsonResponse
		{
			$credentials = validator($this->rpcCredentials($request), [
				'username' => ['required', 'string'],
				'password' => ['required', 'string'],
			])->validate();

			$user = User::where('username', $credentials['username'])->first();

			if (!$user || !$user->password_hash || !Hash::check($credentials['password'], $user->password_hash)) {
				return response()->json(['success' => false, 'message' => 'Invalid credentials']);
			}

			Auth::login($user);
			$request->session()->regenerate();

			return response()->json([
				'success' => true,
				'data' => [
					'success' => true,
					'session' => [
						'user' => $user,
						'token' => $request->session()->getId(),
					],
				],
			]);
		}

		public function register(Request $request): JsonResponse
		{
			$data = validator($this->rpcCredentials($request), [
				'username' => ['required', 'string', 'max:50', 'unique:users,username'],
				'password' => ['required', 'string', 'min:8'],
			])->validate();

			$user = User::create([
				'username' => $data['username'],
				'password_hash' => Hash::make($data['password']),
			]);

			Auth::login($user);
			$request->session()->regenerate();

			return response()->json(['success' => true, 'data' => ['success' => true]]);
		}

		public function logout(Request $request): JsonResponse
		{
			Auth::logout();
			$request->session()->invalidate();
			$request->session()->regenerateToken();

			return response()->json(['success' => true, 'data' => ['success' => true]]);
		}

		public function session(Request $request): JsonResponse
		{
			return response()->json([
				'success' => true,
				'data' => Auth::check()
					? ['user' => Auth::user(), 'token' => $request->session()->getId()]
					: null,
			]);
		}

		public function redirectToGoogle(): RedirectResponse
		{
			return Socialite::driver('google')
				->redirectUrl(route('login.google.callback'))
				->redirect();
		}

		public function handleGoogleCallback(Request $request): RedirectResponse
		{
			try {
				$googleUser = Socialite::driver('google')
					->redirectUrl(route('login.google.callback'))
					->user();

				$user = User::where('google_id', $googleUser->getId())
					->orWhere('email', $googleUser->getEmail())
					->first();

				if ($user) {
					$user->forceFill([
						'email' => $googleUser->getEmail(),
						'google_id' => $googleUser->getId(),
						'google_avatar' => $googleUser->getAvatar(),
					])->save();
				} else {
					$user = User::create([
						'username' => $this->uniqueGoogleUsername($googleUser->getName(), $googleUser->getEmail()),
						'email' => $googleUser->getEmail(),
						'google_id' => $googleUser->getId(),
						'google_avatar' => $googleUser->getAvatar(),
						'password_hash' => null,
					]);
				}

				Auth::login($user);
				$request->session()->regenerate();

				return redirect('/dashboard');
			} catch (Throwable $exception) {
				report($exception);

				return redirect('/login')->with('google_error', true);
			}
		}

		private function uniqueGoogleUsername(?string $name, ?string $email): string
		{
			$base = Str::slug($name ?: Str::before((string)$email, '@'), '_');
			$base = $base ? Str::limit($base, 40, '') : 'google_user';
			$username = $base;
			$suffix = 1;

			while (User::where('username', $username)->exists()) {
				$username = Str::limit($base, 40, '') . '_' . $suffix;
				$suffix++;
			}

			return $username;
		}
	}

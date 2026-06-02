<?php

	namespace App\Http\Controllers\Api;

	use App\Http\Controllers\Controller;
	use Exception;
	use Illuminate\Http\JsonResponse;
	use Illuminate\Http\Request;
	use Illuminate\Support\Facades\Auth;
	use App\Models\User; // MODIFIED: Imported Eloquent User Model
	use Throwable;

	require_once __DIR__ . '/ApiSupport.php';

	class UserApiController extends Controller
	{
		public function setApiKey(Request $request): JsonResponse
		{
			try {
				$channel = 'user:set-api-key';
				$args = $request->input('args', []);
				$args = is_array($args) ? $args : [$args];
				$user = Auth::user();
				$userId = $user?->id;
				$userApiKey = $user?->openrouter_api_key ?? '';

				if ($user) {
					$_SESSION['user'] = $user->only(['id', 'username', 'openrouter_api_key']);
				}

				$result = null;
				do {
					$newKey = $args[0] ?? '';
					// MODIFIED: Replaced users table MySQLi query with standard Eloquent update [1]
					User::where('id', $userId)->update([
						'openrouter_api_key' => $newKey
					]);

					$_SESSION['user']['openrouter_api_key'] = $newKey;
					$result = ['success' => true];
					break;
				} while (false);

				return response()->json(['success' => true, 'data' => $result]);
			} catch (Throwable $exception) {
				return response()->json(['success' => false, 'message' => $exception->getMessage()], 500);
			}
		}

		public function saveModelSettings(Request $request): JsonResponse
		{
			try {
				$args = $request->input('args', []);
				$args = is_array($args) ? $args : [$args];
				$user = Auth::user();
				if (!$user) {
					throw new Exception('Not authenticated.');
				}

				$settings = $args[0] ?? [];
				if (!is_array($settings)) {
					$settings = [];
				}

				$filtered = [];
				foreach ($settings as $key => $value) {
					if (!is_string($key) || !is_scalar($value)) {
						continue;
					}
					$key = trim($key);
					$value = trim((string)$value);
					if ($key !== '' && $value !== '') {
						$filtered[$key] = $value;
					}
				}

				$user->ai_model_settings = $filtered;
				$user->save();

				return response()->json(['success' => true, 'data' => ['success' => true]]);
			} catch (Throwable $exception) {
				return response()->json(['success' => false, 'message' => $exception->getMessage()], 500);
			}
		}
	}

<?php

	namespace App\Http\Controllers\Api;

	use App\Http\Controllers\Controller;
	use Exception;
	use Illuminate\Http\JsonResponse;
	use Illuminate\Http\Request;
	use Illuminate\Support\Facades\Auth;
	use Throwable;

	require_once __DIR__ . '/ApiSupport.php';

	class I18nApiController extends Controller
	{
		public function langFile(Request $request): JsonResponse
		{
			try {
				$channel = 'i18n:get-lang-file';
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
					$lang = $args[0];
					$dir = BASE_DIR . '/lang/' . $lang;
					$merged = [];
					if (is_dir($dir)) {
						foreach (glob($dir . '/*.json') as $file) {
							$key = basename($file, '.json');
							$fileContent = file_get_contents($file);
							$merged[$key] = $fileContent ? json_decode($fileContent, true, 512, JSON_THROW_ON_ERROR) : [];
						}
					}
					$result = json_encode($merged, JSON_THROW_ON_ERROR);
					break;

				} while (false);

				return response()->json(['success' => true, 'data' => $result]);
			} catch (Throwable $exception) {
				return response()->json(['success' => false, 'message' => $exception->getMessage()], 500);
			}
		}
	}

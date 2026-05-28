<?php

	namespace App\Http\Controllers\Api;

	use App\Http\Controllers\Controller;
	use Exception;
	use Illuminate\Http\JsonResponse;
	use Illuminate\Http\Request;
	use Illuminate\Support\Facades\Auth;
	use App\Models\TranslationLog; // MODIFIED: Imported Eloquent model
	use Throwable;

	require_once __DIR__ . '/ApiSupport.php';

	class LogApiController extends Controller
	{
		public function translation(Request $request): JsonResponse
		{
			try {
				$channel = 'log:translation';
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
					$data = $args[0];
					$marker = $data['marker'] ?? null;
					if ($marker && preg_match('/^\[\[#(\d+)\]\]$/', $marker, $matches)) {
						$marker = $matches[1];
					}

					// MODIFIED: Created records with TranslationLog Eloquent Model [1]
					TranslationLog::create([
						'user_id' => $userId,
						'book_id' => $data['bookId'],
						'chapter_id' => $data['chapterId'],
						'source_text' => $data['sourceText'],
						'target_text' => $data['targetText'],
						'marker' => $marker,
						'model' => $data['model'],
						'temperature' => $data['temperature']
					]);

					$result = ['success' => true];
					break;
				} while (false);

				return response()->json(['success' => true, 'data' => $result]);
			} catch (Throwable $exception) {
				return response()->json(['success' => false, 'message' => $exception->getMessage()], 500);
			}
		}
	}

<?php

	namespace App\Http\Controllers\Api;

	use App\Http\Controllers\Controller;
	use Exception;
	use Illuminate\Http\JsonResponse;
	use Illuminate\Http\Request;
	use Illuminate\Support\Facades\Auth;
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
				$db = getDB();
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
					$stmt = $db->prepare('INSERT INTO translation_logs (user_id, book_id, chapter_id, source_text, target_text, marker, model, temperature) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
					$stmt->execute([$userId, $data['bookId'], $data['chapterId'], $data['sourceText'], $data['targetText'], $marker, $data['model'], $data['temperature']]);
					$result = ['success' => true];
					break;

					// --- Dictionaries ---
				} while (false);

				return response()->json(['success' => true, 'data' => $result]);
			} catch (Throwable $exception) {
				return response()->json(['success' => false, 'message' => $exception->getMessage()], 500);
			}
		}
	}

<?php

	namespace App\Http\Controllers\Api;

	use App\Http\Controllers\Controller;
	use Exception;
	use Illuminate\Http\JsonResponse;
	use Illuminate\Http\Request;
	use Illuminate\Support\Facades\Auth;
	use Throwable;

	require_once __DIR__ . '/ApiSupport.php';

	class LogsApiController extends Controller
	{
		public function index(Request $request): JsonResponse
		{
			try {
				$channel = 'logs:get';
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
					$page = max(1, (int)($args[0] ?? 1));
					$limit = 25;
					$offset = ($page - 1) * $limit;
					$total = $db->prepare('SELECT COUNT(*) FROM api_logs WHERE user_id = ?');
					$total->execute([$userId]);
					$countRow = $total->get_result()->fetch_row();
					$count = $countRow[0] ?? 0;

					$stmt = $db->prepare('SELECT id, action, request_payload, response_body, response_code, created_at FROM api_logs WHERE user_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?');
					$stmt->execute([$userId, $limit, $offset]);
					$logs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
					$result = ['logs' => $logs, 'totalPages' => ceil($count / $limit), 'currentPage' => $page];
					break;

					// --- Languages ---
				} while (false);

				return response()->json(['success' => true, 'data' => $result]);
			} catch (Throwable $exception) {
				return response()->json(['success' => false, 'message' => $exception->getMessage()], 500);
			}
		}
	}

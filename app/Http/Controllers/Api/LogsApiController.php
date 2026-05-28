<?php

	namespace App\Http\Controllers\Api;

	use App\Http\Controllers\Controller;
	use Exception;
	use Illuminate\Http\JsonResponse;
	use Illuminate\Http\Request;
	use Illuminate\Support\Facades\Auth;
	use App\Models\ApiLog; // MODIFIED: Imported ApiLog Eloquent Model
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

					// MODIFIED: Eloquent select & paginated retrieval replacing prepare statements [1]
					$count = ApiLog::where('user_id', $userId)->count();

					$logs = ApiLog::select('id', 'action', 'request_payload', 'response_body', 'response_code', 'created_at')
						->where('user_id', $userId)
						->orderBy('created_at', 'DESC')
						->limit($limit)
						->offset($offset)
						->get()
						->toArray();

					$result = ['logs' => $logs, 'totalPages' => ceil($count / $limit), 'currentPage' => $page];
					break;
				} while (false);

				return response()->json(['success' => true, 'data' => $result]);
			} catch (Throwable $exception) {
				return response()->json(['success' => false, 'message' => $exception->getMessage()], 500);
			}
		}
	}

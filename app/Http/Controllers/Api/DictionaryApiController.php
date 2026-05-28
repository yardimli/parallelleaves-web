<?php

	namespace App\Http\Controllers\Api;

	use App\Http\Controllers\Controller;
	use Exception;
	use Illuminate\Http\JsonResponse;
	use Illuminate\Http\Request;
	use Illuminate\Support\Facades\Auth;
	use App\Models\BookDictionary; // NEW: Import the Eloquent BookDictionary model
	use Throwable;

	require_once __DIR__ . '/ApiSupport.php';

	class DictionaryApiController extends Controller
	{
		public function get(Request $request): JsonResponse
		{
			try {
				$channel = 'dictionary:get';
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
					$bookId = $args[0];

					// MODIFIED: Fetches dictionary entries from the database using Eloquent [1]
					$result = BookDictionary::where('book_id', $bookId)
						->orderBy('id', 'asc')
						->get()
						->toArray();
					break;
				} while (false);

				return response()->json(['success' => true, 'data' => $result]);
			} catch (Throwable $exception) {
				return response()->json(['success' => false, 'message' => $exception->getMessage()], 500);
			}
		}

		public function save(Request $request): JsonResponse
		{
			try {
				$channel = 'dictionary:save';
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
					$bookId = $args[0];
					$data = $args[1];

					// MODIFIED: Replacing file_put_contents with Eloquent transactions/queries [1]
					// First, remove existing records to perform a clean sync operation
					BookDictionary::where('book_id', $bookId)->delete();

					// Save non-empty rows into the database
					foreach ($data as $entry) {
						if (!empty($entry['source']) || !empty($entry['target'])) {
							BookDictionary::create([
								'book_id' => $bookId,
								'source' => $entry['source'] ?? '',
								'target' => $entry['target'] ?? '',
								'type' => $entry['type'] ?? 'translation',
							]);
						}
					}

					$result = ['success' => true];
					break;
				} while (false);

				return response()->json(['success' => true, 'data' => $result]);
			} catch (Throwable $exception) {
				return response()->json(['success' => false, 'message' => $exception->getMessage()], 500);
			}
		}

		public function forAi(Request $request): JsonResponse
		{
			try {
				$channel = 'dictionary:getContentForAI';
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
					$bookId = $args[0];
					$type = $args[1];

					// MODIFIED: Fetches dictionary strings from the DB using Eloquent [1]
					$query = BookDictionary::where('book_id', $bookId);
					if ($type) {
						$query->where('type', $type);
					}

					$entries = $query->get();
					$content = '';

					foreach ($entries as $entry) {
						$content .= "{$entry->source} = {$entry->target}\n";
					}

					$result = $content;
					break;
				} while (false);

				return response()->json(['success' => true, 'data' => $result]);
			} catch (Throwable $exception) {
				return response()->json(['success' => false, 'message' => $exception->getMessage()], 500);
			}
		}
	}

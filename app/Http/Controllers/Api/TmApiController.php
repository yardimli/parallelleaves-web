<?php

	namespace App\Http\Controllers\Api;

	use App\Http\Controllers\Controller;
	use Exception;
	use Illuminate\Http\JsonResponse;
	use Illuminate\Http\Request;
	use Illuminate\Support\Facades\Auth;
	use App\Models\UserBook; // MODIFIED: Imported Eloquent Models
	use App\Models\UserBookTranslationMemory;
	use App\Models\UserBookBlock;
	use Throwable;

	require_once __DIR__ . '/ApiSupport.php';

	class TmApiController extends Controller
	{
		public function books(Request $request): JsonResponse
		{
			try {
				$channel = 'tm:getAll';
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
					// MODIFIED: Refactored with sub-query counts via standard Eloquent select mappings [1]
					$result = UserBook::select('id', 'title', 'author', 'source_language', 'target_language')
						->selectSub(function ($query) {
							$query->from('user_books_translation_memory')
								->selectRaw('count(*)')
								->whereColumn('book_id', 'user_books.id');
						}, 'tm_count')
						->where('user_id', $userId)
						->orderBy('updated_at', 'DESC')
						->get()
						->toArray();
					break;
				} while (false);

				return response()->json(['success' => true, 'data' => $result]);
			} catch (Throwable $exception) {
				return response()->json(['success' => false, 'message' => $exception->getMessage()], 500);
			}
		}

		public function details(Request $request): JsonResponse
		{
			try {
				$channel = 'tm:getDetails';
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
					// MODIFIED: Eloquent retrieval replacing raw query prepare calls [1]
					$result = UserBookTranslationMemory::select('source_sentence', 'target_sentence')
						->where('book_id', $bookId)
						->orderBy('id', 'ASC')
						->get()
						->toArray();
					break;
				} while (false);

				return response()->json(['success' => true, 'data' => $result]);
			} catch (Throwable $exception) {
				return response()->json(['success' => false, 'message' => $exception->getMessage()], 500);
			}
		}

		public function delete(Request $request): JsonResponse
		{
			try {
				$channel = 'tm:delete';
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
					// MODIFIED: Refactored deletion process using standard Eloquent calls [1]
					UserBookTranslationMemory::where('book_id', $bookId)->delete();
					UserBookBlock::where('book_id', $bookId)->update(['is_analyzed' => 0]);

					$result = ['success' => true];
					break;
				} while (false);

				return response()->json(['success' => true, 'data' => $result]);
			} catch (Throwable $exception) {
				return response()->json(['success' => false, 'message' => $exception->getMessage()], 500);
			}
		}
	}

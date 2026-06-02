<?php

	namespace App\Http\Controllers\Api;

	use App\Http\Controllers\Controller;
	use Exception;
	use Illuminate\Http\JsonResponse;
	use Illuminate\Http\Request;
	use Illuminate\Support\Facades\Auth;
	use App\Models\UserBook;
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
					// MODIFIED: Included primary record 'id' in results to allow front-end editing and deletion
					$result = UserBookTranslationMemory::select('id', 'source_sentence', 'target_sentence')
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

		// NEW METHOD: Delete an individual segment row in the translation memory
		public function deleteRow(Request $request): JsonResponse
		{
			try {
				$args = $request->input('args', []);
				$args = is_array($args) ? $args : [$args];
				$id = $args[0];

				UserBookTranslationMemory::where('id', $id)->delete();

				return response()->json(['success' => true, 'data' => ['success' => true]]);
			} catch (Throwable $exception) {
				return response()->json(['success' => false, 'message' => $exception->getMessage()], 500);
			}
		}

		// NEW METHOD: Update/Edit an individual segment row in the translation memory
		public function updateRow(Request $request): JsonResponse
		{
			try {
				$args = $request->input('args', []);
				$args = is_array($args) ? $args : [$args];
				$id = $args[0];
				$source = $args[1] ?? '';
				$target = $args[2] ?? '';

				UserBookTranslationMemory::where('id', $id)->update([
					'source_sentence' => $source,
					'target_sentence' => $target
				]);

				return response()->json(['success' => true, 'data' => ['success' => true]]);
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

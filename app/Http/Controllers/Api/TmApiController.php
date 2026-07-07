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
	use Illuminate\Support\Facades\Log;
	use Throwable;

	require_once __DIR__ . '/ApiSupport.php';

	class TmApiController extends Controller
	{
		private function decodeLlmJsonContent(string $content): array
		{
			$content = trim($content);
			if (preg_match('/^```(?:json)?\s*(.*?)\s*```$/is', $content, $matches)) {
				$content = trim($matches[1]);
			}

			$decoded = json_decode($content, true);
			if (!is_array($decoded) && str_contains($content, '\\"')) {
				$decoded = json_decode(stripslashes($content), true);
			}
			if (is_string($decoded)) {
				$decoded = json_decode($decoded, true);
			}

			return is_array($decoded) ? $decoded : [];
		}

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
					$result = UserBookTranslationMemory::select('id', 'source_sentence', 'original_target_sentence', 'edited_target_sentence')
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
				$originalTarget = $args[2] ?? '';
				$editedTarget = $args[3] ?? '';

				UserBookTranslationMemory::where('id', $id)->update([
					'source_sentence' => $source,
					'original_target_sentence' => $originalTarget,
					'edited_target_sentence' => $editedTarget
				]);

				return response()->json(['success' => true, 'data' => ['success' => true]]);
			} catch (Throwable $exception) {
				return response()->json(['success' => false, 'message' => $exception->getMessage()], 500);
			}
		}

		public function purgeRow(Request $request): JsonResponse
		{
			try {
				$args = $request->input('args', []);
				$args = is_array($args) ? $args : [$args];
				$user = Auth::user();
				$userId = $user?->id;
				$userApiKey = $user?->openrouter_api_key ?? '';

				if ($user) {
					$_SESSION['user'] = $user->only(['id', 'username', 'openrouter_api_key']);
				}

				$id = (int)($args[0] ?? 0);
				$rule = trim((string)($args[1] ?? ''));
				$model = trim((string)($args[2] ?? ''));

				if ($id <= 0) {
					throw new Exception('Translation memory row id is required.');
				}
				if ($rule === '') {
					throw new Exception('Purge rule is required.');
				}
				if ($model === '') {
					$model = env('OPEN_ROUTER_MODEL', 'openai/gpt-4o-mini');
				}

				$row = UserBookTranslationMemory::query()
					->join('user_books as b', 'user_books_translation_memory.book_id', '=', 'b.id')
					->where('user_books_translation_memory.id', $id)
					->where('b.user_id', $userId)
					->select(
						'user_books_translation_memory.id',
						'user_books_translation_memory.source_sentence',
						'user_books_translation_memory.original_target_sentence',
						'user_books_translation_memory.edited_target_sentence',
						'b.source_language',
						'b.target_language'
					)
					->first();

				if (!$row) {
					throw new Exception('Translation memory row not found.');
				}

				$systemPrompt = 'You are a strict translation-memory cleanup classifier. Decide whether this entry should be deleted according to the user purge rule. Return only a JSON object with keys "delete" and "reason". "delete" must be true or false. Delete only when the rule clearly matches.';
				$userPrompt = "Purge rule:\n{$rule}\n\nSource language: {$row->source_language}\nTarget language: {$row->target_language}\n\nSource sentence:\n{$row->source_sentence}\n\nOriginal target sentence:\n{$row->original_target_sentence}\n\nEdited target sentence:\n{$row->edited_target_sentence}";

				$payload = [
					'model' => $model,
					'messages' => [
						['role' => 'system', 'content' => $systemPrompt],
						['role' => 'user', 'content' => $userPrompt],
					],
					'temperature' => 0,
					'response_format' => ['type' => 'json_object'],
				];

				$aiResponse = callOpenRouter(
					$payload,
					['userId' => $userId, 'action' => 'tm_purge_row'],
					$userApiKey
				);
				$messageContent = (string)($aiResponse['choices'][0]['message']['content'] ?? '{}');
				$content = $this->decodeLlmJsonContent($messageContent);

				$shouldDelete = (bool)($content['delete'] ?? false);
				if ($shouldDelete) {
					UserBookTranslationMemory::where('id', $id)->delete();
				}

				Log::info('Translation memory purge row result', [
					'user_id' => $userId,
					'row_id' => $id,
					'model' => $model,
					'rule' => $rule,
					'delete' => $shouldDelete,
					'reason' => $content['reason'] ?? null,
				]);

				return response()->json([
					'success' => true,
					'data' => [
						'matched' => $shouldDelete,
						'deleted' => $shouldDelete,
						'reason' => $content['reason'] ?? '',
					],
				]);
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

<?php

	namespace App\Http\Controllers\Api;

	use App\Http\Controllers\Controller;
	use Exception;
	use Illuminate\Http\JsonResponse;
	use Illuminate\Http\Request;
	use Illuminate\Support\Facades\Auth;
	use Throwable;
	use App\Models\Chapter; // MODIFIED: Imported Eloquent Chapter model
	use App\Models\UserBook;
	use App\Models\UserBookBlock;

	require_once __DIR__ . '/ApiSupport.php';

	class ChaptersApiController extends Controller
	{
		private function syncTranslationMemoryBlocks(Chapter $chapter): void
		{
			$pairs = extractAllMarkerPairs($chapter->source_content ?? '', $chapter->target_content ?? '');
			foreach ($pairs as $pair) {
				$markerId = (int)$pair['marker'];
				$sourceText = trim((string)$pair['source']);
				$targetText = trim((string)$pair['target']);
				if ($markerId <= 0 || $sourceText === '' || $targetText === '') {
					continue;
				}

				$block = UserBookBlock::where('book_id', $chapter->book_id)
					->where('marker_id', $markerId)
					->first();

				if (!$block) {
					UserBookBlock::create([
						'book_id' => $chapter->book_id,
						'marker_id' => $markerId,
						'source_text' => $sourceText,
						'target_text' => $targetText,
						'machine_target_text' => $targetText,
						'is_analyzed' => 1,
					]);
					continue;
				}

				$updates = [];
				if ((string)$block->source_text !== $sourceText) {
					$updates['source_text'] = $sourceText;
				}
				if ((string)$block->target_text !== $targetText) {
					$updates['target_text'] = $targetText;
				}
				if (!$block->machine_target_text) {
					$updates['machine_target_text'] = $block->target_text ?: $targetText;
				}
				if (!empty($updates)) {
					$updates['is_analyzed'] = 0;
					$block->update($updates);
				}
			}
		}

		public function updateField(Request $request): JsonResponse
		{
			try {
				$channel = 'chapters:updateField';
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
					$allowedFields = ['title', 'target_content', 'source_content'];
					if (!in_array($data['field'], $allowedFields)) {
						throw new Exception('Invalid field specified.');
					}
					$chapter = Chapter::where('id', $data['chapterId'])->first();
					if (!$chapter) {
						throw new Exception('Chapter not found.');
					}
					$ownsBook = UserBook::where('id', $chapter->book_id)
						->where('user_id', $userId)
						->exists();
					if (!$ownsBook) {
						throw new Exception('Chapter not found.');
					}

					// MODIFIED: Refactored database query to Eloquent update [1]
					$chapter->update([
						$data['field'] => $data['value']
					]);
					if (in_array($data['field'], ['source_content', 'target_content'], true)) {
						$chapter->refresh();
						$this->syncTranslationMemoryBlocks($chapter);
					}
					$result = ['success' => true];
					break;
				} while (false);

				return response()->json(['success' => true, 'data' => $result]);
			} catch (Throwable $exception) {
				return response()->json(['success' => false, 'message' => $exception->getMessage()], 500);
			}
		}

		public function rawContent(Request $request): JsonResponse
		{
			try {
				$channel = 'chapters:getRawContent';
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
					$allowedFields = ['source_content', 'target_content'];
					if (!in_array($data['field'], $allowedFields)) {
						throw new Exception('Invalid field specified.');
					}
					// MODIFIED: Replaced raw database fetch with Eloquent call [1]
					$row = Chapter::select($data['field'])->where('id', $data['chapterId'])->first();
					$result = $row ? $row->{$data['field']} : null;
					break;
				} while (false);

				return response()->json(['success' => true, 'data' => $result]);
			} catch (Throwable $exception) {
				return response()->json(['success' => false, 'message' => $exception->getMessage()], 500);
			}
		}

		public function rename(Request $request): JsonResponse
		{
			try {
				$channel = 'chapters:rename';
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
					// MODIFIED: Refactored renaming statement with Eloquent model [1]
					Chapter::where('id', $data['chapterId'])->update([
						'title' => $data['newTitle']
					]);
					$result = ['success' => true];
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
				$channel = 'chapters:delete';
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
					$chapterId = $data['chapterId'];
					// MODIFIED: Complete Eloquent delete logic with decrement update [1]
					$chapter = Chapter::where('id', $chapterId)->first();
					if ($chapter) {
						$bookId = $chapter->book_id;
						$order = $chapter->chapter_order;
						$chapter->delete();

						Chapter::where('book_id', $bookId)
							->where('chapter_order', '>', $order)
							->decrement('chapter_order');
					}
					$result = ['success' => true];
					break;
				} while (false);

				return response()->json(['success' => true, 'data' => $result]);
			} catch (Throwable $exception) {
				return response()->json(['success' => false, 'message' => $exception->getMessage()], 500);
			}
		}

		public function insert(Request $request): JsonResponse
		{
			try {
				$channel = 'chapters:insert';
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
					$chapterId = $data['chapterId'];
					$direction = $data['direction'];
					// MODIFIED: Standardized chapter insertions with Eloquent increment / save calls [1]
					$ref = Chapter::where('id', $chapterId)->first();
					if ($ref) {
						$bookId = $ref->book_id;
						$newOrder = $direction === 'above' ? $ref->chapter_order : $ref->chapter_order + 1;

						Chapter::where('book_id', $bookId)
							->where('chapter_order', '>=', $newOrder)
							->increment('chapter_order');

						Chapter::create([
							'book_id' => $bookId,
							'title' => 'New Chapter',
							'chapter_order' => $newOrder,
							'source_content' => '<p></p>',
							'target_content' => '<p></p>'
						]);
					}
					$result = ['success' => true];
					break;
				} while (false);

				return response()->json(['success' => true, 'data' => $result]);
			} catch (Throwable $exception) {
				return response()->json(['success' => false, 'message' => $exception->getMessage()], 500);
			}
		}

		public function translationContext(Request $request): JsonResponse
		{
			try {
				$channel = 'chapters:getTranslationContext';
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
					$chapterId = $data['chapterId'];
					$pairCount = $data['pairCount'];
					$selectedText = $data['selectedText'] ?? null;

					if ($pairCount <= 0) {
						$result = [];
						break;
					}

					// MODIFIED: Replaced raw SELECT queries with standard Eloquent calls [1]
					$current = Chapter::select('book_id', 'chapter_order', 'source_content', 'target_content')
						->where('id', $chapterId)
						->first();
					if (!$current) {
						throw new Exception('Chapter not found.');
					}

					$currentPairs = extractMarkerPairsFromHtmlForContext(
						$current->source_content ?? '',
						$current->target_content ?? '',
						$selectedText
					);

					if (count($currentPairs) >= $pairCount) {
						$result = array_slice($currentPairs, -$pairCount);
						break;
					}

					$needed = $pairCount - count($currentPairs);

					// MODIFIED: Fetching preceding chapters context using Chapter Eloquent model
					$prev = Chapter::select('source_content', 'target_content')
						->where('book_id', $current->book_id)
						->where('chapter_order', '<', $current->chapter_order)
						->orderBy('chapter_order', 'DESC')
						->first();

					if (!$prev) {
						$result = $currentPairs;
						break;
					}

					$prevPairs = extractMarkerPairsFromHtmlForContext(
						$prev->source_content ?? '',
						$prev->target_content ?? ''
					);
					$lastPrev = array_slice($prevPairs, -$needed);
					$result = array_merge($lastPrev, $currentPairs);
					break;
				} while (false);

				return response()->json(['success' => true, 'data' => $result]);
			} catch (Throwable $exception) {
				return response()->json(['success' => false, 'message' => $exception->getMessage()], 500);
			}
		}
	}

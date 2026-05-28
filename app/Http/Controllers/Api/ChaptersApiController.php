<?php

	namespace App\Http\Controllers\Api;

	use App\Http\Controllers\Controller;
	use Exception;
	use Illuminate\Http\JsonResponse;
	use Illuminate\Http\Request;
	use Illuminate\Support\Facades\Auth;
	use Throwable;

	require_once __DIR__ . '/ApiSupport.php';

	class ChaptersApiController extends Controller
	{
		public function updateField(Request $request): JsonResponse
		{
			try {
				$channel = 'chapters:updateField';
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
					$allowedFields = ['title', 'target_content', 'source_content'];
					if (!in_array($data['field'], $allowedFields)) {
						throw new Exception('Invalid field specified.');
					}
					$stmt = $db->prepare("UPDATE chapters SET {$data['field']} = ? WHERE id = ?");
					$stmt->execute([$data['value'], $data['chapterId']]);
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
				$db = getDB();
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
					$stmt = $db->prepare("SELECT {$data['field']} FROM chapters WHERE id = ?");
					$stmt->execute([$data['chapterId']]);
					$row = $stmt->get_result()->fetch_row();
					$result = $row[0] ?? null;
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
				$db = getDB();
				$userId = $user?->id;
				$userApiKey = $user?->openrouter_api_key ?? '';

				if ($user) {
					$_SESSION['user'] = $user->only(['id', 'username', 'openrouter_api_key']);
				}

				$result = null;
				do {
					$data = $args[0];
					$db->prepare('UPDATE chapters SET title = ? WHERE id = ?')->execute([$data['newTitle'], $data['chapterId']]);
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
				$db = getDB();
				$userId = $user?->id;
				$userApiKey = $user?->openrouter_api_key ?? '';

				if ($user) {
					$_SESSION['user'] = $user->only(['id', 'username', 'openrouter_api_key']);
				}

				$result = null;
				do {
					$data = $args[0];
					$chapterId = $data['chapterId'];
					$stmt = $db->prepare('SELECT book_id, chapter_order FROM chapters WHERE id = ?');
					$stmt->execute([$chapterId]);
					$chapter = $stmt->get_result()->fetch_assoc();
					if ($chapter) {
						$db->prepare('DELETE FROM chapters WHERE id = ?')->execute([$chapterId]);
						$db->prepare('UPDATE chapters SET chapter_order = chapter_order - 1 WHERE book_id = ? AND chapter_order > ?')->execute([$chapter['book_id'], $chapter['chapter_order']]);
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
				$db = getDB();
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
					$stmt = $db->prepare('SELECT book_id, chapter_order FROM chapters WHERE id = ?');
					$stmt->execute([$chapterId]);
					$ref = $stmt->get_result()->fetch_assoc();
					if ($ref) {
						$newOrder = $direction === 'above' ? $ref['chapter_order'] : $ref['chapter_order'] + 1;
						$db->prepare('UPDATE chapters SET chapter_order = chapter_order + 1 WHERE book_id = ? AND chapter_order >= ?')->execute([$ref['book_id'], $newOrder]);
						$db->prepare('INSERT INTO chapters (book_id, title, chapter_order, source_content, target_content) VALUES (?, ?, ?, ?, ?)')->execute([$ref['book_id'], 'New Chapter', $newOrder, '<p></p>', '<p></p>']);
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
				$db = getDB();
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

					$stmt = $db->prepare('SELECT book_id, chapter_order, source_content, target_content FROM chapters WHERE id = ?');
					$stmt->execute([$chapterId]);
					$current = $stmt->get_result()->fetch_assoc();
					if (!$current) {
						throw new Exception('Chapter not found.');
					}

					$currentPairs = extractMarkerPairsFromHtmlForContext($current['source_content'] ?? '', $current['target_content'] ?? '', $selectedText);

					if (count($currentPairs) >= $pairCount) {
						$result = array_slice($currentPairs, -$pairCount);
						break;
					}

					$needed = $pairCount - count($currentPairs);
					$stmt = $db->prepare('SELECT source_content, target_content FROM chapters WHERE book_id = ? AND chapter_order < ? ORDER BY chapter_order DESC LIMIT 1');
					$stmt->execute([$current['book_id'], $current['chapter_order']]);
					$prev = $stmt->get_result()->fetch_assoc();

					if (!$prev) {
						$result = $currentPairs;
						break;
					}

					$prevPairs = extractMarkerPairsFromHtmlForContext($prev['source_content'] ?? '', $prev['target_content'] ?? '');
					$lastPrev = array_slice($prevPairs, -$needed);
					$result = array_merge($lastPrev, $currentPairs);
					break;

					// --- Documents ---
				} while (false);

				return response()->json(['success' => true, 'data' => $result]);
			} catch (Throwable $exception) {
				return response()->json(['success' => false, 'message' => $exception->getMessage()], 500);
			}
		}
	}

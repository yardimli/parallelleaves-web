<?php

	namespace App\Http\Controllers\Api;

	use App\Http\Controllers\Controller;
	use Exception;
	use Illuminate\Http\JsonResponse;
	use Illuminate\Http\Request;
	use Illuminate\Support\Facades\Auth;
	use Throwable;

	require_once __DIR__ . '/ApiSupport.php';

	class TranslationMemoryApiController extends Controller
	{
		public function start(Request $request): JsonResponse
		{
			try {
				$channel = 'translation-memory:start';
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
					$bookId = $args[0];
					$stmt = $db->prepare('SELECT source_content, target_content FROM chapters WHERE book_id = ?');
					$stmt->execute([$bookId]);
					$chapters = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
					$allPairs = [];
					foreach ($chapters as $ch) {
						$allPairs = array_merge($allPairs, extractAllMarkerPairs($ch['source_content'] ?? '', $ch['target_content'] ?? ''));
					}
					$db->prepare('DELETE from user_book_blocks WHERE book_id = ?')->execute([$bookId]);
					$stmt = $db->prepare('INSERT INTO user_book_blocks (book_id, marker_id, source_text, target_text, is_analyzed) VALUES (?, ?, ?, ?, 0)');
					foreach ($allPairs as $pair) {
						$stmt->execute([$bookId, $pair['marker'], $pair['source'], $pair['target']]);
					}
					$count = count($allPairs);
					if ($count > 0) {
						$db->prepare('INSERT INTO tm_generation_jobs (book_id, total_blocks) VALUES (?, ?)')->execute([$bookId, $count]);
						$result = ['job_id' => $db->insert_id, 'total_blocks' => $count];
					} else {
						$result = ['job_id' => null];
					}
					break;
				} while (false);

				return response()->json(['success' => true, 'data' => $result]);
			} catch (Throwable $exception) {
				return response()->json(['success' => false, 'message' => $exception->getMessage()], 500);
			}
		}

		public function processBatch(Request $request): JsonResponse
		{
			try {
				$channel = 'translation-memory:process-batch';
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
					$jobId = $args[0];
					$stmt = $db->prepare('SELECT * FROM tm_generation_jobs WHERE id = ?');
					$stmt->execute([$jobId]);
					$job = $stmt->get_result()->fetch_assoc();
					if (!$job || $job['status'] === 'complete') {
						$result = ['status' => 'complete', 'processed_blocks' => $job['processed_blocks'] ?? 0];
						break;
					}
					$bookId = $job['book_id'];
					$stmt = $db->prepare('SELECT source_language, target_language FROM user_books WHERE id = ?');
					$stmt->execute([$bookId]);
					$book = $stmt->get_result()->fetch_assoc();
					$blockStmt = $db->prepare('SELECT * from user_book_blocks WHERE book_id = ? AND is_analyzed = 0 LIMIT 1');
					$blockStmt->execute([$bookId]);
					$block = $blockStmt->get_result()->fetch_assoc();

					if (!$block) {
						$db->prepare("UPDATE tm_generation_jobs SET status = 'complete' WHERE id = ?")->execute([$jobId]);
						$result = ['status' => 'complete', 'processed_blocks' => $job['total_blocks']];
					} else {
						$systemPrompt = "You are a literary translation analyst. Your task is to analyze a pair of texts—an original and its translation—and generate concise, actionable translation examples for an AI translator to imitate the style of the human translator. Return your response as a single JSON object with one key: 'pairs'. The value of 'pairs' must be an array of objects, where each object has two keys: 'source' and 'target'.";
						$userPrompt = "Analyze the following pair and generate exactly 2 translation pair(s) that best reflect the translator's style.\n\nSource ({$book['source_language']}):\n{$block['source_text']}\n\nTranslation ({$book['target_language']}):\n{$block['target_text']}";

						$payload = [
							'model' => OPEN_ROUTER_MODEL,
							'messages' => [
								['role' => 'system', 'content' => $systemPrompt],
								['role' => 'user', 'content' => $userPrompt]
							],
							'temperature' => 0.7,
							'response_format' => ['type' => 'json_object']
						];

						// MODIFIED: Passed $userApiKey
						$aiResponse = callOpenRouter($payload, ['db' => $db, 'userId' => $userId, 'action' => 'tm_llm_call'], $userApiKey);
						$content = json_decode($aiResponse['choices'][0]['message']['content'] ?? '{}', true);

						if (isset($content['pairs'])) {
							$insertStmt = $db->prepare('INSERT INTO user_books_translation_memory (book_id, block_id, source_sentence, target_sentence) VALUES (?, ?, ?, ?)');
							foreach ($content['pairs'] as $pair) {
								$insertStmt->execute([$bookId, $block['id'], $pair['source'], $pair['target']]);
							}
						}

						$db->prepare('UPDATE user_book_blocks SET is_analyzed = 1 WHERE id = ?')->execute([$block['id']]);
						$db->prepare('UPDATE tm_generation_jobs SET processed_blocks = processed_blocks + 1 WHERE id = ?')->execute([$jobId]);
						$result = ['status' => 'running', 'processed_blocks' => $job['processed_blocks'] + 1, 'total_blocks' => $job['total_blocks']];
					}
					break;

					// --- Codex ---
				} while (false);

				return response()->json(['success' => true, 'data' => $result]);
			} catch (Throwable $exception) {
				return response()->json(['success' => false, 'message' => $exception->getMessage()], 500);
			}
		}
	}

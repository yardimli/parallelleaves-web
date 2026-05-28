<?php

	namespace App\Http\Controllers\Api;

	use App\Http\Controllers\Controller;
	use Exception;
	use Illuminate\Http\JsonResponse;
	use Illuminate\Http\Request;
	use Illuminate\Support\Facades\Auth;
	use Throwable;

	require_once __DIR__ . '/ApiSupport.php';

	class LlmApiController extends Controller
	{
		public function processText(Request $request): JsonResponse
		{
			try {
				$channel = 'llm:process-text';
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
					$messages = $data['messages'] ?? [];
					if (isset($data['prompt'])) {
						$messages = [];
						if (!empty($data['prompt']['system'])) {
							$messages[] = ['role' => 'system', 'content' => $data['prompt']['system']];
						}
						if (!empty($data['prompt']['context_pairs'])) {
							$messages = array_merge($messages, $data['prompt']['context_pairs']);
						}
						if (!empty($data['prompt']['user'])) {
							$messages[] = ['role' => 'user', 'content' => $data['prompt']['user']];
						}
					}

					$bookId = $data['bookId'] ?? null;
					$tmContent = '';
					$codexContent = '';

					if ($bookId) {
						$hasTmPlaceholder = false;
						$hasCodexPlaceholder = false;
						foreach ($messages as $msg) {
							if ($msg['role'] === 'system') {
								if (strpos($msg['content'], '##TRANSLATION_MEMORY##') !== false) {
									$hasTmPlaceholder = true;
								}
								if (strpos($msg['content'], '##CODEX_BLOCK##') !== false) {
									$hasCodexPlaceholder = true;
								}
							}
						}

						// MODIFIED: Replaced the old TM logic with the advanced two-pass REGEXP logic
						if ($hasTmPlaceholder) {
							$lastUserMessage = '';
							for ($i = count($messages) - 1; $i >= 0; $i--) {
								if ($messages[$i]['role'] === 'user') {
									$lastUserMessage = $messages[$i]['content'];
									break;
								}
							}

							// Split into words, filter length > 2
							$words = preg_split('/[\s,.;:!?()"-]+/', strtolower($lastUserMessage), -1, PREG_SPLIT_NO_EMPTY);
							$uniqueWords = array_unique($words);
							$uniqueWords = array_filter($uniqueWords, fn($w) => mb_strlen($w) > 2);

							if (!empty($uniqueWords)) {
								$allMemories = [];
								$maxPairs = 100;

								// First pass - get memories from the current novel first.
								if ($bookId) {
									$stmt = $db->prepare(
										"SELECT tm.id, tm.source_sentence, tm.target_sentence, b.source_language, b.target_language " .
										"FROM user_books_translation_memory tm " .
										"JOIN user_books b ON tm.book_id = b.id " .
										"WHERE (b.user_id = ? OR ? = 1) AND b.id = ? AND tm.source_sentence REGEXP ?"
									);

									foreach ($uniqueWords as $word) {
										if (count($allMemories) >= $maxPairs) {
											break;
										}
										$regexpPattern = '[[:<:]]' . $db->real_escape_string($word) . '[[:>:]]';
										$stmt->execute([$userId, $userId, $bookId, $regexpPattern]);
										$memoriesForWord = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

										if (count($memoriesForWord) > 3) {
											continue;
										}

										foreach ($memoriesForWord as $memory) {
											if (count($allMemories) >= $maxPairs) {
												break 2;
											}
											$allMemories[$memory['id']] = $memory;
										}
									}
								}

								// Second pass - fill remaining slots with memories from other novels.
								if (count($allMemories) < $maxPairs) {
									$sql = "SELECT tm.id, tm.source_sentence, tm.target_sentence, b.source_language, b.target_language " .
										"FROM user_books_translation_memory tm " .
										"JOIN user_books b ON tm.book_id = b.id " .
										"WHERE (b.user_id = ? OR ? = 1) ";
									if ($bookId) {
										$sql .= "AND b.id != ? ";
									}
									$sql .= "AND tm.source_sentence REGEXP ?";
									$stmt = $db->prepare($sql);

									foreach ($uniqueWords as $word) {
										if (count($allMemories) >= $maxPairs) {
											break;
										}
										$regexpPattern = '[[:<:]]' . $db->real_escape_string($word) . '[[:>:]]';

										if ($bookId) {
											$stmt->execute([$userId, $userId, $bookId, $regexpPattern]);
										} else {
											$stmt->execute([$userId, $userId, $regexpPattern]);
										}

										$memoriesForWord = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

										if (count($memoriesForWord) > 3) {
											continue;
										}

										foreach ($memoriesForWord as $memory) {
											if (count($allMemories) >= $maxPairs) {
												break 2;
											}
											if (!isset($allMemories[$memory['id']])) {
												$allMemories[$memory['id']] = $memory;
											}
										}
									}
								}

								// Format the unique TM pairs for injection
								foreach ($allMemories as $mem) {
									$tmContent .= "<{$mem['source_language']}>{$mem['source_sentence']}</{$mem['source_language']}>\n";
									$tmContent .= "<{$mem['target_language']}>{$mem['target_sentence']}</{$mem['target_language']}>\n";
								}
								$tmContent = trim($tmContent);
							}
						}

						if ($hasCodexPlaceholder) {
							$stmt = $db->prepare("SELECT codex_content FROM user_books WHERE id = ? AND user_id = ?");
							$stmt->execute([$bookId, $userId]);
							$row = $stmt->get_result()->fetch_assoc();
							if ($row && !empty($row['codex_content'])) {
								$codexContent = "Use the following glossary for consistent translation:\n<glossary>\n" . $row['codex_content'] . "\n</glossary>";
							}
						}

						foreach ($messages as &$msg) {
							if ($msg['role'] === 'system') {
								if ($hasTmPlaceholder) {
									if ($tmContent) {
										$msg['content'] = str_replace('##TRANSLATION_MEMORY##', $tmContent, $msg['content']);
									} else {
										$msg['content'] = preg_replace("/Use the following translation examples to guide the translation:\n##TRANSLATION_MEMORY##\n*/", '', $msg['content']);
									}
								}
								if ($hasCodexPlaceholder) {
									if ($codexContent) {
										$msg['content'] = str_replace('##CODEX_BLOCK##', $codexContent, $msg['content']);
									} else {
										$msg['content'] = str_replace("##CODEX_BLOCK##\n", '', $msg['content']);
									}
								}
								$msg['content'] = trim(preg_replace('/\n{3,}/', "\n\n", $msg['content']));
							}
						}
						unset($msg);
					}

					// MODIFIED: Added prompt length check to prevent exceeding API limits
					$promptLength = 0;
					foreach ($messages as $msg) {
						if (isset($msg['content'])) {
							$promptLength += strlen($msg['content']);
						}
					}

					if ($promptLength > 100000) {
						throw new Exception('The total length of the prompt is more than 100000 characters.');
					}

					$payload = [
						'model' => $data['model'] ?? OPEN_ROUTER_MODEL,
						'messages' => $messages,
						'temperature' => $data['temperature'] ?? 0.7
					];
					$logCtx = ['db' => $db, 'userId' => $userId, 'action' => 'llm_process_text'];
					// MODIFIED: Passed $userApiKey
					$result = ['success' => true, 'data' => callOpenRouter($payload, $logCtx, $userApiKey)];
					break;
				} while (false);

				return response()->json(['success' => true, 'data' => $result]);
			} catch (Throwable $exception) {
				return response()->json(['success' => false, 'message' => $exception->getMessage()], 500);
			}
		}
	}

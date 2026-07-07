<?php

	namespace App\Http\Controllers\Api;

	use App\Http\Controllers\Controller;
	use Exception;
	use Illuminate\Http\JsonResponse;
	use Illuminate\Http\Request;
	use Illuminate\Support\Facades\Auth;
	use Illuminate\Support\Facades\DB; // MODIFIED: Imported DB Facade [1]
	use App\Models\UserBook; // MODIFIED: Imported Eloquent Models
	use Throwable;

	require_once __DIR__ . '/ApiSupport.php';

	class ChatApiController extends Controller
	{
		public function sendMessage(Request $request): JsonResponse
		{
			try {
				$channel = 'chat:send-message';
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
					$styleAnalysisContent = '';
					$codexContent = '';

					if ($bookId) {
						$hasTmPlaceholder = false;
						$hasStyleAnalysisPlaceholder = false;
						$hasCodexPlaceholder = false;
						foreach ($messages as $msg) {
							if ($msg['role'] === 'system') {
								if (strpos($msg['content'], '##TRANSLATION_MEMORY##') !== false) {
									$hasTmPlaceholder = true;
								}
								if (strpos($msg['content'], '##STYLE_ANALYSIS_BLOCK##') !== false) {
									$hasStyleAnalysisPlaceholder = true;
								}
								if (strpos($msg['content'], '##CODEX_BLOCK##') !== false) {
									$hasCodexPlaceholder = true;
								}
							}
						}

						if ($hasTmPlaceholder) {
							$lastUserMessage = '';
							for ($i = count($messages) - 1; $i >= 0; $i--) {
								if ($messages[$i]['role'] === 'user') {
									$lastUserMessage = $messages[$i]['content'];
									break;
								}
							}

							$words = preg_split('/[\s,.;:!?()"-]+/', strtolower($lastUserMessage), -1, PREG_SPLIT_NO_EMPTY);
							$uniqueWords = array_unique($words);
							$uniqueWords = array_filter($uniqueWords, fn($w) => mb_strlen($w) > 2);

							if (!empty($uniqueWords)) {
								$allMemories = [];
								$maxPairs = 100;

								if ($bookId) {
									foreach ($uniqueWords as $word) {
										if (count($allMemories) >= $maxPairs) {
											break;
										}

										// MODIFIED: Dynamic REGEXP execution with parameters through DB Facade [1]
										$regexpPattern = '[[:<:]]' . $word . '[[:>:]]';
										$memoriesForWord = DB::table('user_books_translation_memory as tm')
											->join('user_books as b', 'tm.book_id', '=', 'b.id')
											->select('tm.id', 'tm.source_sentence', 'tm.edited_target_sentence', 'b.source_language', 'b.target_language')
											->where(function ($query) use ($userId) {
												$query->where('b.user_id', $userId)
													->orWhereRaw('? = 1', [$userId]);
											})
											->where('b.id', $bookId)
											->whereRaw('tm.source_sentence REGEXP ?', [$regexpPattern])
											->get()
											->map(fn($item) => (array)$item)
											->all();

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

								if (count($allMemories) < $maxPairs) {
									foreach ($uniqueWords as $word) {
										if (count($allMemories) >= $maxPairs) {
											break;
										}
										$regexpPattern = '[[:<:]]' . $word . '[[:>:]]';

										// MODIFIED: Dynamic REGEXP execution with standard Query Builder queries [1]
										$query = DB::table('user_books_translation_memory as tm')
											->join('user_books as b', 'tm.book_id', '=', 'b.id')
											->select('tm.id', 'tm.source_sentence', 'tm.edited_target_sentence', 'b.source_language', 'b.target_language')
											->where(function ($q) use ($userId) {
												$q->where('b.user_id', $userId)
													->orWhereRaw('? = 1', [$userId]);
											});

										if ($bookId) {
											$query->where('b.id', '!=', $bookId);
										}

										$memoriesForWord = $query->whereRaw('tm.source_sentence REGEXP ?', [$regexpPattern])
											->get()
											->map(fn($item) => (array)$item)
											->all();

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

								foreach ($allMemories as $mem) {
									$tmContent .= "<{$mem['source_language']}>{$mem['source_sentence']}</{$mem['source_language']}>\n";
									$tmContent .= "<{$mem['target_language']}>{$mem['edited_target_sentence']}</{$mem['target_language']}>\n";
								}
								$tmContent = trim($tmContent);
							}
						}

						if ($hasStyleAnalysisPlaceholder) {
							$row = UserBook::select('style_analysis_content')
								->where('id', $bookId)
								->where('user_id', $userId)
								->first();

							if ($row && !empty($row->style_analysis_content)) {
								$styleAnalysisContent = "Use the following source style analysis and translation guidance before glossary/codex instructions:\n<style_analysis>\n" . $row->style_analysis_content . "\n</style_analysis>";
							}
						}

						if ($hasCodexPlaceholder) {
							// MODIFIED: Replaced raw prepare with clean Eloquent SELECT [1]
							$row = UserBook::select('codex_content')
								->where('id', $bookId)
								->where('user_id', $userId)
								->first();

							if ($row && !empty($row->codex_content)) {
								$codexContent = "Use the following glossary for consistent translation:\n<glossary>\n" . $row->codex_content . "\n</glossary>";
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
								if ($hasStyleAnalysisPlaceholder) {
									if ($styleAnalysisContent) {
										$msg['content'] = str_replace('##STYLE_ANALYSIS_BLOCK##', $styleAnalysisContent, $msg['content']);
									} else {
										$msg['content'] = str_replace("##STYLE_ANALYSIS_BLOCK##\n", '', $msg['content']);
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

					$promptLength = 0;
					foreach ($messages as $msg) {
						if (isset($msg['content'])) {
							$promptLength += strlen($msg['content']);
						}
					}

					if ($promptLength > 160000) {
						throw new Exception('The total length of the prompt is more than 160000 characters.');
					}

					$payload = [
						'model' => $data['model'] ?? env('OPEN_ROUTER_MODEL', 'openai/gpt-4o-mini'),
						'messages' => $messages,
						'temperature' => $data['temperature'] ?? 0.7
					];
					// MODIFIED: Removed raw $db reference in log context configuration
					$logCtx = ['userId' => $userId, 'action' => 'llm_process_text'];
					$result = ['success' => true, 'data' => callOpenRouter($payload, $logCtx, $userApiKey)];
					break;
				} while (false);

				return response()->json(['success' => true, 'data' => $result]);
			} catch (Throwable $exception) {
				return response()->json(['success' => false, 'message' => $exception->getMessage()], 500);
			}
		}
	}

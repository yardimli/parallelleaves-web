<?php

	namespace App\Http\Controllers\Api;

	use App\Http\Controllers\Controller;
	use Exception;
	use Illuminate\Http\JsonResponse;
	use Illuminate\Http\Request;
	use Illuminate\Support\Facades\Auth;
	use Illuminate\Support\Facades\DB; // MODIFIED: Imported DB Facade [1]
	use Illuminate\Support\Facades\Log;
	use App\Models\UserBook; // MODIFIED: Imported Eloquent Models
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

							$tmContent = app(\App\Services\TranslationMemoryPrompt::class)->content($bookId, $userId, $lastUserMessage);
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
							// MODIFIED: Eloquent replacement for fetching codex metadata [1]
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
					$action = (string)($data['action'] ?? 'unknown');
					Log::info('LLM prompt', [
						'user_id' => $userId,
						'book_id' => $bookId,
						'action' => $action,
						'model' => $payload['model'],
						'temperature' => $payload['temperature'],
						'messages' => $messages,
						'payload' => $payload,
					]);
					// MODIFIED: Sanitized array configuration passed without $db context
					$logCtx = ['userId' => $userId, 'action' => 'llm_process_text'];
					$llmResponse = callOpenRouter($payload, $logCtx, $userApiKey);
					Log::info('LLM result', [
						'user_id' => $userId,
						'book_id' => $bookId,
						'action' => $action,
						'model' => $payload['model'],
						'raw_response' => $llmResponse,
						'message_content' => $llmResponse['choices'][0]['message']['content'] ?? null,
					]);
					$result = ['success' => true, 'data' => $llmResponse];
					break;
				} while (false);

				return response()->json(['success' => true, 'data' => $result]);
			} catch (Throwable $exception) {
				return response()->json(['success' => false, 'message' => $exception->getMessage()], 500);
			}
		}
	}

<?php

	namespace App\Http\Controllers\Api;

	use App\Http\Controllers\Controller;
	use Exception;
	use Illuminate\Http\JsonResponse;
	use Illuminate\Http\Request;
	use Illuminate\Support\Facades\Auth;
	use Illuminate\Support\Facades\Log;
	use App\Models\UserBookBlock;
	use App\Models\UserBookTranslationMemory;
	use App\Models\UserBook;
	use Throwable;

	require_once __DIR__ . '/ApiSupport.php';

	class TranslationMemoryApiController extends Controller
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

		public function start(Request $request): JsonResponse
		{
			try {
				$args = $request->input('args', []);
				$args = is_array($args) ? $args : [$args];
				$userId = Auth::id();
				$bookId = $args[0] ?? null;
				$ownsBook = UserBook::where('id', $bookId)->where('user_id', $userId)->exists();
				if (!$ownsBook) {
					throw new Exception('Book not found.');
				}

				$pending = UserBookBlock::where('book_id', $bookId)->where('is_analyzed', 0)->count();
				return response()->json(['success' => true, 'data' => [
					'status' => $pending > 0 ? 'pending' : 'complete',
					'total_blocks' => $pending,
				]]);
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
				$userId = $user?->id;
				$userApiKey = $user?->openrouter_api_key ?? '';

				if ($user) {
					$_SESSION['user'] = $user->only(['id', 'username', 'openrouter_api_key']);
				}

				$result = null;
				do {
					$bookId = $args[0];
					$book = UserBook::where('id', $bookId)->where('user_id', $userId)->first();
					if (!$book) {
						throw new Exception('Book not found.');
					}

					$modelSettings = is_array($user?->ai_model_settings) ? $user->ai_model_settings : [];
					$model = $modelSettings['parallel-leaves-ai-model']
						?? $modelSettings['translation_model']
						?? env('OPEN_ROUTER_MODEL', 'openai/gpt-4o-mini');

					$systemPrompt = "You are a literary translation analyst. Identify only the specific sentences whose {$book->target_language} translation was changed by the user compared with the original machine translation. Return a single JSON object with one key, \"pairs\". Each pair must contain exactly these keys: \"source_sentence\" for the matching {$book->source_language} source sentence, \"original_target_sentence\" for the original machine {$book->target_language} sentence, and \"edited_target_sentence\" for the user-edited {$book->target_language} sentence. Do not put {$book->source_language} text in either target field. Do not include unchanged surrounding sentences.";
					$pendingBlocks = UserBookBlock::where('book_id', $bookId)
						->where('is_analyzed', 0)
						->orderBy('id')
						->limit(5)
						->get();

					$processedCount = 0;
					foreach ($pendingBlocks as $block) {
						UserBookTranslationMemory::where('block_id', $block->id)->delete();
						$sourceText = (string)$block->source_text;
						$originalTargetText = (string)($block->machine_target_text ?: $block->target_text);
						$changedTargetText = (string)$block->target_text;

						if (trim($sourceText) === '' || trim($changedTargetText) === '' || trim($originalTargetText) === trim($changedTargetText)) {
							$block->update(['is_analyzed' => 1]);
							$processedCount++;
							continue;
						}

						$userPrompt = "Analyze the following translation update for a {$book->source_language} to {$book->target_language} project. Compare the original machine {$book->target_language} translation with the current user-edited {$book->target_language} translation. Identify only the edited sentences.\n\n{$book->source_language} source segment:\n{$sourceText}\n\nOriginal machine {$book->target_language} translation:\n{$originalTargetText}\n\nCurrent user-edited {$book->target_language} translation:\n{$changedTargetText}";

						$payload = [
							'model' => $model,
							'messages' => [
								['role' => 'system', 'content' => $systemPrompt],
								['role' => 'user', 'content' => $userPrompt]
							],
							'temperature' => 0.7,
							'response_format' => ['type' => 'json_object']
						];

						Log::info('Translation memory LLM prompt', [
							'user_id' => $userId,
							'book_id' => $bookId,
							'block_id' => $block->id,
							'marker_id' => $block->marker_id,
							'model' => $model,
							'system_prompt' => $systemPrompt,
							'user_prompt' => $userPrompt,
							'payload' => $payload,
						]);

						$aiResponse = callOpenRouter(
							$payload,
							['userId' => $userId, 'action' => 'tm_llm_call'],
							$userApiKey
						);
						$messageContent = (string)($aiResponse['choices'][0]['message']['content'] ?? '{}');
						$content = $this->decodeLlmJsonContent($messageContent);

						Log::info('Translation memory LLM result', [
							'user_id' => $userId,
							'book_id' => $bookId,
							'block_id' => $block->id,
							'marker_id' => $block->marker_id,
							'model' => $model,
							'raw_response' => $aiResponse,
							'message_content' => $messageContent,
							'decoded_content' => $content,
						]);

						if (isset($content['pairs'])) {
							foreach ($content['pairs'] as $pair) {
								$sourceSentence = trim((string)($pair['source_sentence'] ?? $pair['source'] ?? ''));
								$originalTargetSentence = trim((string)($pair['original_target_sentence'] ?? ''));
								$editedTargetSentence = trim((string)($pair['edited_target_sentence'] ?? $pair['target'] ?? ''));
								if ($sourceSentence === '' || $originalTargetSentence === '' || $editedTargetSentence === '') {
									continue;
								}
								UserBookTranslationMemory::create([
									'book_id' => $bookId,
									'block_id' => $block->id,
									'source_sentence' => $sourceSentence,
									'original_target_sentence' => $originalTargetSentence,
									'edited_target_sentence' => $editedTargetSentence
								]);
							}
						}
						$block->update(['is_analyzed' => 1]);
						$processedCount++;
					}

					$remaining = UserBookBlock::where('book_id', $bookId)->where('is_analyzed', 0)->count();
					$result = [
						'status' => $remaining > 0 ? 'pending' : 'complete',
						'processedCount' => $processedCount,
						'remainingCount' => $remaining,
					];
					break;
				} while (false);

				return response()->json(['success' => true, 'data' => $result]);
			} catch (Throwable $exception) {
				return response()->json(['success' => false, 'message' => $exception->getMessage()], 500);
			}
		}
	}

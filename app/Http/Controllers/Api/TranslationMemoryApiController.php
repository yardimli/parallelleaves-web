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

					$systemPrompt = "You are a literary translation analyst. Your task is to identify and analyze the specific sentences that the user/translator has modified or polished in the translation, compared to their previous translation. Focus ONLY on those modified sentences. Do not include unedited surrounding sentences. Generate translation pairs ONLY for the edited/modified sentences to capture the translator's unique stylistic choices. Return your response as a single JSON object with one key: 'pairs'. The value of 'pairs' must be an array of objects, where each object has two keys: 'source' and 'target'.";
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

						$userPrompt = "Analyze the following translation update. Compare the original machine translation with the current user-edited translation. Identify the specific sentences that were edited or modified. Focus only on those changed sentences and do not include unedited sentences above or below them. Generate translation pairs representing only those specific changes.\n\nSource Segment ({$book->source_language}):\n{$sourceText}\n\nOriginal Machine Translation ({$book->target_language}):\n{$originalTargetText}\n\nCurrent User Translation ({$book->target_language}):\n{$changedTargetText}";

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
								$sourceSentence = trim((string)($pair['source'] ?? ''));
								$targetSentence = trim((string)($pair['target'] ?? ''));
								if ($sourceSentence === '' || $targetSentence === '') {
									continue;
								}
								UserBookTranslationMemory::create([
									'book_id' => $bookId,
									'block_id' => $block->id,
									'source_sentence' => $sourceSentence,
									'target_sentence' => $targetSentence
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

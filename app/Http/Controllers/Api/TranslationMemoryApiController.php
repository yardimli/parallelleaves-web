<?php

	namespace App\Http\Controllers\Api;

	use App\Http\Controllers\Controller;
	use Exception;
	use Illuminate\Http\JsonResponse;
	use Illuminate\Http\Request;
	use Illuminate\Support\Facades\Auth;
	use App\Models\UserBookBlock;
	use App\Models\UserBookTranslationMemory;
	use Throwable;

	require_once __DIR__ . '/ApiSupport.php';

	class TranslationMemoryApiController extends Controller
	{
		/**
		 * Bypassed Legacy Start Method
		 */
		public function start(Request $request): JsonResponse
		{
			return response()->json(['success' => true, 'data' => ['job_id' => null]]);
		}

		/**
		 * MODIFIED: Process the segment changes synchronously without background job polling.
		 * Uses posted data directly and avoids querying book content from the database.
		 */
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
					$payloadData = $args[1] ?? [];

					$sourceLanguage = $payloadData['sourceLanguage'] ?? 'Source';
					$targetLanguage = $payloadData['targetLanguage'] ?? 'Translation';
					$changes = $payloadData['changes'] ?? [];

					// MODIFIED: Restructure system instructions to focus only on edited segments
					$systemPrompt = "You are a literary translation analyst. Your task is to identify and analyze the specific sentences that the user/translator has modified or polished in the translation, compared to their previous translation. Focus ONLY on those modified sentences. Do not include unedited surrounding sentences. Generate translation pairs ONLY for the edited/modified sentences to capture the translator's unique stylistic choices. Return your response as a single JSON object with one key: 'pairs'. The value of 'pairs' must be an array of objects, where each object has two keys: 'source' and 'target'.";

					foreach ($changes as $item) {
						$markerId = $item['markerId'];
						$sourceText = $item['sourceText'] ?? '';
						$originalTargetText = $item['originalTargetText'] ?? '';
						$changedTargetText = $item['changedTargetText'] ?? '';

						// Find or create UserBookBlock matching this book and marker ID
						$block = UserBookBlock::where('book_id', $bookId)
							->where('marker_id', $markerId)
							->first();

						if ($block) {
							UserBookTranslationMemory::where('block_id', $block->id)->delete();
							$block->update([
								'source_text' => $sourceText,
								'target_text' => $changedTargetText,
								'is_analyzed' => 1
							]);
						} else {
							$block = UserBookBlock::create([
								'book_id' => $bookId,
								'marker_id' => $markerId,
								'source_text' => $sourceText,
								'target_text' => $changedTargetText,
								'is_analyzed' => 1
							]);
						}

						// MODIFIED: Re-written prompt compares previous translation with current translation and extracts sentence changes
						$userPrompt = "Analyze the following translation update. Compare the previous translation with the current modified translation. Identify the specific sentences that were edited or modified. Focus only on those changed sentences and do not include unedited sentences above or below them. Generate translation pairs representing only those specific changes.\n\nSource Segment ({$sourceLanguage}):\n{$sourceText}\n\nPrevious Translation ({$targetLanguage}):\n{$originalTargetText}\n\nCurrent Modified Translation ({$targetLanguage}):\n{$changedTargetText}";

						$payload = [
							'model' => env('OPEN_ROUTER_MODEL', 'openai/gpt-4o-mini'),
							'messages' => [
								['role' => 'system', 'content' => $systemPrompt],
								['role' => 'user', 'content' => $userPrompt]
							],
							'temperature' => 0.7,
							'response_format' => ['type' => 'json_object']
						];

						$aiResponse = callOpenRouter(
							$payload,
							['userId' => $userId, 'action' => 'tm_llm_call'],
							$userApiKey
						);
						$content = json_decode($aiResponse['choices'][0]['message']['content'] ?? '{}', true);

						if (isset($content['pairs'])) {
							foreach ($content['pairs'] as $pair) {
								UserBookTranslationMemory::create([
									'book_id' => $bookId,
									'block_id' => $block->id,
									'source_sentence' => $pair['source'],
									'target_sentence' => $pair['target']
								]);
							}
						}
					}

					$result = [
						'status' => 'complete',
						'processedCount' => count($changes)
					];
					break;
				} while (false);

				return response()->json(['success' => true, 'data' => $result]);
			} catch (Throwable $exception) {
				return response()->json(['success' => false, 'message' => $exception->getMessage()], 500);
			}
		}
	}

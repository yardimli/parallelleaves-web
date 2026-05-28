<?php

	namespace App\Http\Controllers\Api;

	use App\Http\Controllers\Controller;
	use Exception;
	use Illuminate\Http\JsonResponse;
	use Illuminate\Http\Request;
	use Illuminate\Support\Facades\Auth;
	use App\Models\Chapter; // MODIFIED: Imported Eloquent Models
	use App\Models\UserBook;
	use App\Models\UserBookBlock;
	use App\Models\TmGenerationJob;
	use App\Models\UserBookTranslationMemory;
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
				$userId = $user?->id;
				$userApiKey = $user?->openrouter_api_key ?? '';

				if ($user) {
					$_SESSION['user'] = $user->only(['id', 'username', 'openrouter_api_key']);
				}

				$result = null;
				do {
					$bookId = $args[0];
					// MODIFIED: Standard select using Chapter Eloquent model [1]
					$chapters = Chapter::select('source_content', 'target_content')->where('book_id', $bookId)->get();
					$allPairs = [];
					foreach ($chapters as $ch) {
						$allPairs = array_merge($allPairs, extractAllMarkerPairs($ch->source_content ?? '', $ch->target_content ?? ''));
					}

					// MODIFIED: standard Eloquent deletions and insertions replacing prepared raw statements [1]
					UserBookBlock::where('book_id', $bookId)->delete();
					foreach ($allPairs as $pair) {
						UserBookBlock::create([
							'book_id' => $bookId,
							'marker_id' => $pair['marker'],
							'source_text' => $pair['source'],
							'target_text' => $pair['target'],
							'is_analyzed' => 0
						]);
					}

					$count = count($allPairs);
					if ($count > 0) {
						$job = TmGenerationJob::create([
							'book_id' => $bookId,
							'total_blocks' => $count
						]);
						$result = ['job_id' => $job->id, 'total_blocks' => $count];
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
				$userId = $user?->id;
				$userApiKey = $user?->openrouter_api_key ?? '';

				if ($user) {
					$_SESSION['user'] = $user->only(['id', 'username', 'openrouter_api_key']);
				}

				$result = null;
				do {
					$jobId = $args[0];
					// MODIFIED: Standard Eloquent retrieve replaces raw preparation structures [1]
					$job = TmGenerationJob::where('id', $jobId)->first();
					if (!$job || $job->status === 'complete') {
						$result = ['status' => 'complete', 'processed_blocks' => $job->processed_blocks ?? 0];
						break;
					}
					$bookId = $job->book_id;

					$book = UserBook::select('source_language', 'target_language')->where('id', $bookId)->first();
					if (!$book) {
						throw new Exception('Book details not found.');
					}

					$block = UserBookBlock::where('book_id', $bookId)->where('is_analyzed', 0)->first();

					if (!$block) {
						$job->update(['status' => 'complete']);
						$result = ['status' => 'complete', 'processed_blocks' => $job->total_blocks];
					} else {
						$systemPrompt = "You are a literary translation analyst. Your task is to analyze a pair of texts—an original and its translation—and generate concise, actionable translation examples for an AI translator to imitate the style of the human translator. Return your response as a single JSON object with one key: 'pairs'. The value of 'pairs' must be an array of objects, where each object has two keys: 'source' and 'target'.";
						$userPrompt = "Analyze the following pair and generate exactly 2 translation pair(s) that best reflect the translator's style.\n\nSource ({$book->source_language}):\n{$block->source_text}\n\nTranslation ({$book->target_language}):\n{$block->target_text}";

						$payload = [
							'model' => env('OPEN_ROUTER_MODEL', 'openai/gpt-4o-mini'),
							'messages' => [
								['role' => 'system', 'content' => $systemPrompt],
								['role' => 'user', 'content' => $userPrompt]
							],
							'temperature' => 0.7,
							'response_format' => ['type' => 'json_object']
						];

						// MODIFIED: Log call sanitized of raw database elements
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

						// MODIFIED: Replaced raw blocks & jobs updates with standard Eloquent updates [1]
						$block->update(['is_analyzed' => 1]);
						$job->increment('processed_blocks');

						$result = [
							'status' => 'running',
							'processed_blocks' => $job->processed_blocks,
							'total_blocks' => $job->total_blocks
						];
					}
					break;
				} while (false);

				return response()->json(['success' => true, 'data' => $result]);
			} catch (Throwable $exception) {
				return response()->json(['success' => false, 'message' => $exception->getMessage()], 500);
			}
		}
	}

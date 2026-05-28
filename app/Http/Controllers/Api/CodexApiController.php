<?php

	namespace App\Http\Controllers\Api;

	use App\Http\Controllers\Controller;
	use Exception;
	use Illuminate\Http\JsonResponse;
	use Illuminate\Http\Request;
	use Illuminate\Support\Facades\Auth;
	use Illuminate\Support\Facades\DB; // MODIFIED: Imported DB Facade [1]
	use App\Models\UserBook; // MODIFIED: Imported Eloquent Models
	use App\Models\Chapter;
	use App\Models\UserBookCodexChunk;
	use Throwable;

	require_once __DIR__ . '/ApiSupport.php';

	class CodexApiController extends Controller
	{
		public function books(Request $request): JsonResponse
		{
			try {
				$channel = 'codex:getAll';
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
					// MODIFIED: Refactored with Eloquent UserBook model query [1]
					$result = UserBook::select('id', 'title', 'author', 'source_language', 'target_language', 'codex_status')
						->where('user_id', $userId)
						->orderBy('updated_at', 'DESC')
						->get()
						->toArray();
					break;
				} while (false);

				return response()->json(['success' => true, 'data' => $result]);
			} catch (Throwable $exception) {
				return response()->json(['success' => false, 'message' => $exception->getMessage()], 500);
			}
		}

		public function details(Request $request): JsonResponse
		{
			try {
				$channel = 'codex:getDetails';
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
					// MODIFIED: Using Eloquent select/where clauses [1]
					$book = UserBook::select('id', 'title', 'codex_content', 'codex_status')
						->where('id', $bookId)
						->where('user_id', $userId)
						->first();
					$result = $book ? $book->toArray() : null;
					break;
				} while (false);

				return response()->json(['success' => true, 'data' => $result]);
			} catch (Throwable $exception) {
				return response()->json(['success' => false, 'message' => $exception->getMessage()], 500);
			}
		}

		public function save(Request $request): JsonResponse
		{
			try {
				$channel = 'codex:save';
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
					$content = $args[1];
					// MODIFIED: Replaced raw update query with Eloquent model update [1]
					UserBook::where('id', $bookId)
						->where('user_id', $userId)
						->update(['codex_content' => $content]);

					$result = ['success' => true];
					break;
				} while (false);

				return response()->json(['success' => true, 'data' => $result]);
			} catch (Throwable $exception) {
				return response()->json(['success' => false, 'message' => $exception->getMessage()], 500);
			}
		}

		public function reset(Request $request): JsonResponse
		{
			try {
				$channel = 'codex:reset';
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
					// MODIFIED: Replaced reset logic with Eloquent updates and deletes [1]
					UserBook::where('id', $bookId)
						->where('user_id', $userId)
						->update([
							'codex_content' => null,
							'codex_status' => 'none',
							'codex_chunks_total' => 0,
							'codex_chunks_processed' => 0
						]);

					UserBookCodexChunk::where('book_id', $bookId)->delete();

					$result = ['success' => true];
					break;
				} while (false);

				return response()->json(['success' => true, 'data' => $result]);
			} catch (Throwable $exception) {
				return response()->json(['success' => false, 'message' => $exception->getMessage()], 500);
			}
		}

		public function start(Request $request): JsonResponse
		{
			try {
				$channel = 'codex:start';
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

					// MODIFIED: Using Eloquent selectRaw on chunks metadata [1]
					$chunkStats = UserBookCodexChunk::selectRaw('COUNT(*) as total, SUM(is_processed) as processed')
						->where('book_id', $bookId)
						->first();

					if ($chunkStats && $chunkStats->total > 0) {
						$totalChunks = (int)$chunkStats->total;
						$processedChunks = (int)$chunkStats->processed;

						if ($processedChunks >= $totalChunks) {
							UserBook::where('id', $bookId)->update([
								'codex_status' => 'complete',
								'codex_chunks_total' => $totalChunks,
								'codex_chunks_processed' => $processedChunks
							]);
							$result = ['status' => 'complete'];
						} else {
							UserBook::where('id', $bookId)->update([
								'codex_status' => 'generating',
								'codex_chunks_total' => $totalChunks,
								'codex_chunks_processed' => $processedChunks
							]);
							$result = ['status' => 'generating'];
						}
					} else {
						// MODIFIED: Fetching chapters via standard Eloquent select and chunk formatting
						$chapters = Chapter::select('source_content')->where('book_id', $bookId)->get();
						$fullText = '';
						foreach ($chapters as $c) {
							$fullText .= htmlToPlainText($c->source_content ?? '') . "\n";
						}
						$words = preg_split('/\s+/', $fullText);
						$chunks = array_chunk($words, 8000);
						$totalChunks = count($chunks);

						if ($totalChunks > 0) {
							foreach ($chunks as $i => $chunk) {
								UserBookCodexChunk::create([
									'book_id' => $bookId,
									'chunk_index' => $i,
									'chunk_text' => implode(' ', $chunk),
									'is_processed' => 0
								]);
							}

							UserBook::where('id', $bookId)->update([
								'codex_status' => 'generating',
								'codex_chunks_total' => $totalChunks,
								'codex_chunks_processed' => 0
							]);
							$result = ['status' => 'generating'];
						} else {
							UserBook::where('id', $bookId)->update([
								'codex_status' => 'complete',
								'codex_chunks_total' => 0,
								'codex_chunks_processed' => 0
							]);
							$result = ['status' => 'complete'];
						}
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
				$channel = 'codex:process-batch';
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
					// MODIFIED: Refactored batch selection logic via standard Eloquent queries [1]
					$book = UserBook::where('id', $bookId)->first();
					if (!$book) {
						throw new Exception('Book not found.');
					}

					$chunk = UserBookCodexChunk::where('book_id', $bookId)
						->where('is_processed', 0)
						->orderBy('chunk_index', 'ASC')
						->first();

					if (!$chunk) {
						UserBook::where('id', $bookId)->update(['codex_status' => 'complete']);
						$result = ['status' => 'complete'];
					} else {
						$systemPrompt = "You are a meticulous world-building assistant for a bookist. Your task is to maintain a codex (an encyclopedia of the world). Identify new characters, locations, or lore from the text chunk and integrate them. Your output must be the complete, updated codex in {$book->target_language}.";
						$userPrompt = "**Existing Codex Content:**\n<codex>\n" . ($book->codex_content ?? 'This is the beginning of the codex.') . "\n</codex>\n\n**Text Chunk to Analyze (in {$book->source_language} - limit 8000 words):**\n<text>\n{$chunk->chunk_text}\n</text>";

						$payload = [
							'model' => env('OPEN_ROUTER_MODEL', 'openai/gpt-4o-mini'),
							'messages' => [
								['role' => 'system', 'content' => $systemPrompt],
								['role' => 'user', 'content' => $userPrompt]
							],
							'temperature' => 0.5
						];

						$aiResponse = callOpenRouter(
							$payload,
							['userId' => $userId, 'action' => 'codex_llm_call'],
							$userApiKey
						);
						$updatedCodexText = trim($aiResponse['choices'][0]['message']['content'] ?? '');

						if ($updatedCodexText) {
							UserBook::where('id', $bookId)->update([
								'codex_content' => $updatedCodexText,
								'codex_chunks_processed' => DB::raw('codex_chunks_processed + 1')
							]);
						}

						UserBookCodexChunk::where('id', $chunk->id)->update(['is_processed' => 1]);

						$result = [
							'status' => 'generating',
							'processed' => $book->codex_chunks_processed + 1,
							'total' => $book->codex_chunks_total
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

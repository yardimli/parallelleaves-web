<?php

	namespace App\Http\Controllers\Api;

	use App\Http\Controllers\Controller;
	use Exception;
	use Illuminate\Http\JsonResponse;
	use Illuminate\Http\Request;
	use Illuminate\Support\Facades\Auth;
	use Illuminate\Support\Facades\DB;
	use App\Models\UserBook;
	use App\Models\Chapter;
	use App\Models\UserBookCodexChunk;
	use Throwable;

	require_once __DIR__ . '/ApiSupport.php';

	class CodexApiController extends Controller
	{
		private function cleanCodexText(string $text): string
		{
			$text = trim($text);
			do {
				$previous = $text;
				$text = preg_replace('/^\s*<codex>\s*/i', '', $text);
				$text = preg_replace('/\s*<\/codex>\s*$/i', '', $text);
				$text = trim($text);
			} while ($text !== $previous);

			return $text;
		}

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
					$result = UserBook::select('id', 'title', 'author', 'source_language', 'target_language', 'codex_status', 'codex_chunks_total', 'codex_chunks_processed')
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
					$book = UserBook::select('id', 'title', 'source_language', 'target_language', 'codex_content', 'codex_status', 'codex_chunks_total', 'codex_chunks_processed')
						->where('id', $bookId)
						->where('user_id', $userId)
						->first();
					$result = $book ? $book->toArray() : null;
					if ($result && isset($result['codex_content'])) {
						$result['codex_content'] = $this->cleanCodexText((string)$result['codex_content']);
					}
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
					$content = $this->cleanCodexText((string)$args[1]);
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
					$options = $args[1] ?? [];
					$forceRebuild = is_array($options) && !empty($options['rebuild']);

					if ($forceRebuild) {
						UserBookCodexChunk::where('book_id', $bookId)->delete();
						UserBook::where('id', $bookId)
							->where('user_id', $userId)
							->update([
								'codex_content' => null,
								'codex_status' => 'none',
								'codex_chunks_total' => 0,
								'codex_chunks_processed' => 0
							]);
					}

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
					$options = $args[1] ?? [];
					$model = is_array($options) && !empty($options['model'])
						? (string)$options['model']
						: env('OPEN_ROUTER_MODEL', 'openai/gpt-4o-mini');
					$temperature = is_array($options) && isset($options['temperature'])
						? max(0, min(2, (float)$options['temperature']))
						: 0.5;
					$book = UserBook::where('id', $bookId)->where('user_id', $userId)->first();
					if (!$book) {
						throw new Exception('Book not found.');
					}

					$codexLanguage = is_array($options) && !empty($options['codex_language'])
						? (string)$options['codex_language']
						: $book->target_language;

					// NEW: If "both" is selected, configure the target instructions to demand both languages.
					if ($codexLanguage === 'both') {
						$langInstruction = "both {$book->source_language} and {$book->target_language}";
					} else {
						$langInstruction = $codexLanguage;
					}

					$chunk = UserBookCodexChunk::where('book_id', $bookId)
						->where('is_processed', 0)
						->orderBy('chunk_index', 'ASC')
						->first();

					if (!$chunk) {
						UserBook::where('id', $bookId)->update(['codex_status' => 'complete']);
						$result = ['status' => 'complete'];
					} else {
						// MODIFIED: Injected $langInstruction variables into the output formatting constraints
						$systemPrompt = "You are a meticulous world-building assistant. Maintain a plain-text world codex for this book. Identify new characters, locations, terminology, continuity notes, or lore from the text chunk and integrate them into the existing codex. Output only the complete updated codex as plain text in {$langInstruction}. Do not wrap the answer in XML, HTML, Markdown fences, or <codex> tags.";
						$userPrompt = "Existing codex content:\n" . ($book->codex_content ?? 'This is the beginning of the codex.') . "\n\nText chunk to analyze (in {$book->source_language}, limit 8000 words):\n{$chunk->chunk_text}";

						$payload = [
							'model' => $model,
							'messages' => [
								['role' => 'system', 'content' => $systemPrompt],
								['role' => 'user', 'content' => $userPrompt]
							],
							'temperature' => $temperature
						];

						$aiResponse = callOpenRouter(
							$payload,
							['userId' => $userId, 'action' => 'codex_llm_call'],
							$userApiKey
						);
						$updatedCodexText = $this->cleanCodexText((string)($aiResponse['choices'][0]['message']['content'] ?? ''));

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
							'total' => $book->codex_chunks_total,
							'codex_content' => $updatedCodexText
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

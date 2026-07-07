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

		private function countPlainWords(string $text): int
		{
			return count(preg_split('/\s+/u', trim(strip_tags($text)), -1, PREG_SPLIT_NO_EMPTY) ?: []);
		}

		private function normalizeCodexStatus(UserBook $book): UserBook
		{
			$total = (int)($book->codex_chunks_total ?? 0);
			$processed = (int)($book->codex_chunks_processed ?? 0);
			if ($total > 0 && $processed >= $total && $book->codex_status !== 'complete') {
				$book->forceFill([
					'codex_status' => 'complete',
					'codex_chunks_processed' => $total,
				])->save();
				$book->refresh();
			}

			return $book;
		}

		private function sourceWordsForBook(int|string $bookId): array
		{
			$chapters = Chapter::select('source_content')->where('book_id', $bookId)->orderBy('chapter_order')->get();
			$fullText = '';
			foreach ($chapters as $chapter) {
				$fullText .= htmlToPlainText($chapter->source_content ?? '') . "\n";
			}

			return preg_split('/\s+/', trim($fullText), -1, PREG_SPLIT_NO_EMPTY) ?: [];
		}

		private function outputLanguageInstruction(UserBook $book, mixed $language): string
		{
			$language = is_string($language) && $language !== '' ? $language : 'English';
			return $language === 'both'
				? "both {$book->source_language} and {$book->target_language}"
				: $language;
		}

		private function compactStyleAnalysisText(string $text): string
		{
			$text = trim(strip_tags($text));
			$lines = preg_split('/\R/', $text) ?: [];
			$kept = [];
			foreach ($lines as $line) {
				$trimmed = trim($line);
				if ($trimmed === '') {
					if (!empty($kept) && end($kept) !== '') {
						$kept[] = '';
					}
					continue;
				}
				if (preg_match('/^(updated style analysis|overall\b|block\s+\d+\b)/i', $trimmed)) {
					continue;
				}
				$kept[] = $trimmed;
			}
			$text = trim(preg_replace("/\n{3,}/", "\n\n", implode("\n", $kept)));
			$words = preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
			if (count($words) >= 500) {
				$text = implode(' ', array_slice($words, 0, 499));
			}

			return $text;
		}

		private function compactCodexText(
			UserBook $book,
			string $content,
			string $model,
			float $temperature,
			mixed $language,
			int $userId,
			string $userApiKey,
			int $targetWordCount = 1000
		): string {
			$targetWordCount = max(1, min(1000, $targetWordCount));
			$originalWordCount = $this->countPlainWords($content);
			$langInstruction = $this->outputLanguageInstruction($book, $language);

			$payload = [
				'model' => $model,
				'messages' => [
					[
						'role' => 'system',
						'content' => "You compact book codex notes for translators and continuity tracking. This codex is a translation aid, not a book summary. Preserve translation-critical facts such as character gender/pronouns, names, relationships, forms of address, locations, terminology, invented words, lore terms, and continuity details that affect wording. Remove plot recap, repetition, and minor wording. Keep the final codex under {$targetWordCount} words. Write in {$langInstruction}. Output only the compacted plain-text codex. Do not use XML, HTML, Markdown fences, or commentary.",
					],
					[
						'role' => 'user',
						'content' => "Compact this codex from {$originalWordCount} words to under {$targetWordCount} words:\n\n{$content}",
					],
				],
				'temperature' => $temperature,
			];

			$aiResponse = callOpenRouter($payload, ['userId' => $userId, 'action' => 'codex_compact_llm_call'], $userApiKey);
			$compacted = $this->cleanCodexText((string)($aiResponse['choices'][0]['message']['content'] ?? ''));
			if ($compacted === '') {
				throw new Exception('The model returned an empty compacted codex.');
			}

			return $compacted;
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
						->map(fn(UserBook $book) => $this->normalizeCodexStatus($book))
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
					$book = UserBook::select(
						'id',
						'title',
						'source_language',
						'target_language',
						'codex_content',
						'codex_status',
						'codex_chunks_total',
						'codex_chunks_processed',
						'style_analysis_content',
						'style_analysis_status',
						'style_analysis_percent',
						'style_analysis_chunks_total',
						'style_analysis_chunks_processed'
					)
						->where('id', $bookId)
						->where('user_id', $userId)
						->first();
					if ($book) {
						$book = $this->normalizeCodexStatus($book);
					}
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

		public function compact(Request $request): JsonResponse
		{
			try {
				$args = $request->input('args', []);
				$args = is_array($args) ? $args : [$args];
				$user = Auth::user();
				$userId = $user?->id;
				$userApiKey = $user?->openrouter_api_key ?? '';

				$bookId = $args[0];
				$options = $args[1] ?? [];
				$options = is_array($options) ? $options : [];
				$percent = max(10, min(75, (int)($options['percent'] ?? 25)));
				$content = $this->cleanCodexText((string)($options['content'] ?? ''));
				$model = !empty($options['model'])
					? (string)$options['model']
					: env('OPEN_ROUTER_MODEL', 'openai/gpt-4o-mini');
				$temperature = isset($options['temperature'])
					? max(0, min(2, (float)$options['temperature']))
					: 0.3;

				$book = UserBook::where('id', $bookId)->where('user_id', $userId)->first();
				if (!$book) {
					throw new Exception('Book not found.');
				}
				if ($content === '') {
					throw new Exception('There is no codex content to compact.');
				}

				$originalWordCount = $this->countPlainWords($content);
				$targetWordCount = min(1000, max(1, (int)ceil($originalWordCount * ((100 - $percent) / 100))));
				$compacted = $this->compactCodexText(
					$book,
					$content,
					$model,
					$temperature,
					$options['codex_language'] ?? 'English',
					(int)$userId,
					$userApiKey,
					$targetWordCount
				);

				$book->update(['codex_content' => $compacted]);

				return response()->json(['success' => true, 'data' => [
					'success' => true,
					'codex_content' => $compacted,
					'original_word_count' => $originalWordCount,
					'compacted_word_count' => $this->countPlainWords($compacted),
				]]);
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

					$langInstruction = $this->outputLanguageInstruction($book, $options['codex_language'] ?? 'English');

					$chunk = UserBookCodexChunk::where('book_id', $bookId)
						->where('is_processed', 0)
						->orderBy('chunk_index', 'ASC')
						->first();

					if (!$chunk) {
						UserBook::where('id', $bookId)->update(['codex_status' => 'complete']);
						$result = ['status' => 'complete'];
					} else {
						// MODIFIED: Injected $langInstruction variables into the output formatting constraints
						$systemPrompt = "You maintain a plain-text translation codex for this book. The codex is there to help translation consistency, not to summarize the book. Keep only translation-critical reference notes: character names, gender/pronouns, relationships, forms of address, recurring titles, locations, terminology, invented words, lore terms, and continuity facts that affect wording. Remove plot recap, redundant details, and scene summaries. Integrate new useful facts from the text chunk into the existing codex while keeping the complete updated codex under 1000 words. Output only the complete updated codex as plain text in {$langInstruction}. Do not wrap the answer in XML, HTML, Markdown fences, or <codex> tags.";
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

						$processedAfter = (int)$book->codex_chunks_processed + 1;
						$totalChunks = (int)$book->codex_chunks_total;
						$isComplete = $totalChunks > 0 && $processedAfter >= $totalChunks;

						$codexWasCompacted = false;
						$codexWordCountBeforeCompaction = $this->countPlainWords($updatedCodexText);
						if ($updatedCodexText && $codexWordCountBeforeCompaction > 1200) {
							$updatedCodexText = $this->compactCodexText(
								$book,
								$updatedCodexText,
								$model,
								$temperature,
								$options['codex_language'] ?? 'English',
								(int)$userId,
								$userApiKey,
								1000
							);
							$codexWasCompacted = true;
						}

						if ($updatedCodexText) {
							UserBook::where('id', $bookId)->update([
								'codex_content' => $updatedCodexText,
								'codex_chunks_processed' => DB::raw('codex_chunks_processed + 1'),
								'codex_status' => $isComplete ? 'complete' : 'generating',
							]);
						}

						UserBookCodexChunk::where('id', $chunk->id)->update(['is_processed' => 1]);

						$result = [
							'status' => $isComplete ? 'complete' : 'generating',
							'processed' => $processedAfter,
							'total' => $totalChunks,
							'codex_content' => $updatedCodexText,
							'codex_compacted' => $codexWasCompacted,
							'codex_word_count' => $this->countPlainWords($updatedCodexText),
						];
					}
					break;
				} while (false);

				return response()->json(['success' => true, 'data' => $result]);
			} catch (Throwable $exception) {
				return response()->json(['success' => false, 'message' => $exception->getMessage()], 500);
			}
		}

		public function saveStyleAnalysis(Request $request): JsonResponse
		{
			try {
				$args = $request->input('args', []);
				$args = is_array($args) ? $args : [$args];
				$userId = Auth::id();

				$bookId = $args[0];
				$content = trim((string)($args[1] ?? ''));
				UserBook::where('id', $bookId)
					->where('user_id', $userId)
					->update(['style_analysis_content' => $content]);

				return response()->json(['success' => true, 'data' => ['success' => true]]);
			} catch (Throwable $exception) {
				return response()->json(['success' => false, 'message' => $exception->getMessage()], 500);
			}
		}

		public function startStyleAnalysis(Request $request): JsonResponse
		{
			try {
				$args = $request->input('args', []);
				$args = is_array($args) ? $args : [$args];
				$userId = Auth::id();

				$bookId = $args[0];
				$options = $args[1] ?? [];
				$percent = is_array($options) && isset($options['percent'])
					? max(5, min(100, (int)$options['percent']))
					: 5;
				$rebuild = is_array($options) && !empty($options['rebuild']);

				$book = UserBook::where('id', $bookId)->where('user_id', $userId)->first();
				if (!$book) {
					throw new Exception('Book not found.');
				}

				$words = $this->sourceWordsForBook($bookId);
				if (count($words) === 0) {
					$book->update([
						'style_analysis_status' => 'complete',
						'style_analysis_percent' => $percent,
						'style_analysis_chunks_total' => 0,
						'style_analysis_chunks_processed' => 0,
					]);
					return response()->json(['success' => true, 'data' => ['status' => 'complete']]);
				}

				$book->update([
					'style_analysis_content' => $rebuild ? null : $book->style_analysis_content,
					'style_analysis_status' => 'generating',
					'style_analysis_percent' => $percent,
					'style_analysis_chunks_total' => 1,
					'style_analysis_chunks_processed' => 0,
				]);

				return response()->json(['success' => true, 'data' => ['status' => 'generating']]);
			} catch (Throwable $exception) {
				return response()->json(['success' => false, 'message' => $exception->getMessage()], 500);
			}
		}

		public function processStyleAnalysisBatch(Request $request): JsonResponse
		{
			try {
				$args = $request->input('args', []);
				$args = is_array($args) ? $args : [$args];
				$user = Auth::user();
				$userId = $user?->id;
				$userApiKey = $user?->openrouter_api_key ?? '';

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

				$langInstruction = $this->outputLanguageInstruction($book, $options['codex_language'] ?? 'English');
				$percent = is_array($options) && isset($options['percent'])
					? max(5, min(100, (int)$options['percent']))
					: max(5, min(100, (int)($book->style_analysis_percent ?: 5)));

				$words = $this->sourceWordsForBook($bookId);
				$selectedWordCount = max(1, (int)ceil(count($words) * ($percent / 100)));
				$sampleText = implode(' ', array_slice($words, 0, $selectedWordCount));
				if (trim($sampleText) === '') {
					$book->update(['style_analysis_status' => 'complete']);
					return response()->json(['success' => true, 'data' => [
						'status' => 'complete',
						'processed' => 0,
						'total' => 0,
						'style_analysis_content' => $book->style_analysis_content,
					]]);
				}

				$systemPrompt = "You are a literary translation analyst. Create one strict, practical style guide for translating this source text from {$book->source_language} to {$book->target_language}. Write in {$langInstruction}. Output fewer than 500 words total. Do not include introductions, block labels, summaries, or an Overall section. Use brief imperative bullets only. Focus on voice/register, sentence rhythm, dialogue handling, recurring terms/images, and concrete translation risks.";
				$userPrompt = "Analyze the following source sample. It contains the first {$percent}% of the source text and should be the only source material used for creating the style instructions:\n\n{$sampleText}";

				$payload = [
					'model' => $model,
					'messages' => [
						['role' => 'system', 'content' => $systemPrompt],
						['role' => 'user', 'content' => $userPrompt],
					],
					'temperature' => $temperature,
				];

				$aiResponse = callOpenRouter($payload, ['userId' => $userId, 'action' => 'style_analysis_llm_call'], $userApiKey);
				$updatedAnalysis = $this->compactStyleAnalysisText((string)($aiResponse['choices'][0]['message']['content'] ?? ''));
				if ($updatedAnalysis === '') {
					throw new Exception('The model returned an empty style analysis.');
				}

				$book->update([
					'style_analysis_content' => $updatedAnalysis,
					'style_analysis_status' => 'complete',
					'style_analysis_percent' => $percent,
					'style_analysis_chunks_total' => 1,
					'style_analysis_chunks_processed' => 1,
				]);

				return response()->json(['success' => true, 'data' => [
					'status' => 'complete',
					'processed' => 1,
					'total' => 1,
					'style_analysis_content' => $updatedAnalysis,
				]]);
			} catch (Throwable $exception) {
				return response()->json(['success' => false, 'message' => $exception->getMessage()], 500);
			}
		}
	}

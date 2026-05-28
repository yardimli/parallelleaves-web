<?php

	namespace App\Http\Controllers\Api;

	use App\Http\Controllers\Controller;
	use Exception;
	use Illuminate\Http\JsonResponse;
	use Illuminate\Http\Request;
	use Illuminate\Support\Facades\Auth;
	use Throwable;
	use App\Models\UserBook; // MODIFIED: Imported Eloquent Models
	use App\Models\Chapter;
	use App\Models\Image;

	require_once __DIR__ . '/ApiSupport.php';

	class BooksApiController extends Controller
	{
		public function withCovers(Request $request): JsonResponse
		{
			try {
				$channel = 'books:getAllWithCovers';
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
					// MODIFIED: Refactored to utilize Eloquent with left join and custom count [1]
					$books = UserBook::select('user_books.*', 'images.image_local_path as cover_path')
						->leftJoin('images', 'user_books.id', '=', 'images.book_id')
						->withCount('chapters as chapter_count')
						->where('user_books.user_id', $userId)
						->orderBy('user_books.updated_at', 'desc')
						->get()
						->toArray();

					foreach ($books as &$book) {
						// MODIFIED: Eloquent replacement for fetching chapters and calculating word counts
						$chapters = Chapter::select('source_content', 'target_content')
							->where('book_id', $book['id'])
							->get()
							->toArray();

						$book['source_word_count'] = array_sum(array_map(
							fn($c) => countWordsInHtml($c['source_content'] ?? ''),
							$chapters
						));
						$book['target_word_count'] = array_sum(array_map(
							fn($c) => countWordsInHtml($c['target_content'] ?? ''),
							$chapters
						));
						if ($book['cover_path']) {
							$book['cover_path'] = '/storage/userData/images/' . $book['cover_path'];
						}
					}
					$result = $books;
					break;
				} while (false);

				return response()->json(['success' => true, 'data' => $result]);
			} catch (Throwable $exception) {
				return response()->json(['success' => false, 'message' => $exception->getMessage()], 500);
			}
		}

		public function withTranslationMemory(Request $request): JsonResponse
		{
			try {
				$channel = 'books:getAllWithTranslationMemory';
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
					// MODIFIED: Refactored to standard Eloquent query with join & distinct [1]
					$result = UserBook::select('user_books.id', 'user_books.title')
						->join('user_books_translation_memory as tm', 'user_books.id', '=', 'tm.book_id')
						->distinct()
						->where('user_books.user_id', $userId)
						->orderBy('user_books.title', 'ASC')
						->get()
						->toArray();
					break;
				} while (false);

				return response()->json(['success' => true, 'data' => $result]);
			} catch (Throwable $exception) {
				return response()->json(['success' => false, 'message' => $exception->getMessage()], 500);
			}
		}

		public function get(Request $request): JsonResponse
		{
			try {
				$channel = 'books:getOne';
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
					// MODIFIED: Eloquent model call instead of raw select [1]
					$bookModel = UserBook::where('id', $bookId)->where('user_id', $userId)->first();
					if ($bookModel) {
						$book = $bookModel->toArray();

						// MODIFIED: Fetch chapters via standard query builder/eloquent
						$chapters = Chapter::where('book_id', $bookId)->orderBy('chapter_order')->get()->toArray();
						foreach ($chapters as &$chapter) {
							$chapter['source_word_count'] = countWordsInHtml($chapter['source_content'] ?? '');
							$chapter['target_word_count'] = countWordsInHtml($chapter['target_content'] ?? '');
						}
						$book['chapters'] = $chapters;
						$result = $book;
					}
					break;
				} while (false);

				return response()->json(['success' => true, 'data' => $result]);
			} catch (Throwable $exception) {
				return response()->json(['success' => false, 'message' => $exception->getMessage()], 500);
			}
		}

		public function fullManuscript(Request $request): JsonResponse
		{
			try {
				$channel = 'books:getFullManuscript';
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
					// MODIFIED: Refactored database operations with Eloquent models [1]
					$bookModel = UserBook::where('id', $bookId)->where('user_id', $userId)->first();
					if ($bookModel) {
						$book = $bookModel->toArray();
						$chapters = Chapter::where('book_id', $bookId)->orderBy('chapter_order')->get()->toArray();
						foreach ($chapters as &$chapter) {
							$chapter['source_word_count'] = countWordsInHtml($chapter['source_content'] ?? '');
							$chapter['target_word_count'] = countWordsInHtml($chapter['target_content'] ?? '');
						}
						$book['chapters'] = $chapters;
						$result = $book;
					}
					break;
				} while (false);

				return response()->json(['success' => true, 'data' => $result]);
			} catch (Throwable $exception) {
				return response()->json(['success' => false, 'message' => $exception->getMessage()], 500);
			}
		}

		public function allContent(Request $request): JsonResponse
		{
			try {
				$channel = 'books:getAllBookContent';
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
					// MODIFIED: Eloquent replacement for pulling raw source & target contents [1]
					$chapters = Chapter::select('source_content', 'target_content')->where('book_id', $bookId)->get();
					$combined = '';
					foreach ($chapters as $c) {
						$combined .= ($c->source_content ?? '') . ($c->target_content ?? '');
					}
					$result = ['success' => true, 'combinedHtml' => $combined];
					break;
				} while (false);

				return response()->json(['success' => true, 'data' => $result]);
			} catch (Throwable $exception) {
				return response()->json(['success' => false, 'message' => $exception->getMessage()], 500);
			}
		}

		public function forExport(Request $request): JsonResponse
		{
			try {
				$channel = 'books:getForExport';
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
					// MODIFIED: Refactored with Eloquent standard retrieval [1]
					$bookModel = UserBook::select('id', 'title', 'author', 'target_language')
						->where('id', $bookId)
						->where('user_id', $userId)
						->first();
					if (!$bookModel) {
						throw new Exception('Book not found.');
					}
					$book = $bookModel->toArray();
					$book['chapters'] = Chapter::select('id', 'title', 'target_content')
						->where('book_id', $bookId)
						->orderBy('chapter_order')
						->get()
						->toArray();
					$result = ['success' => true, 'data' => $book];
					break;
				} while (false);

				return response()->json(['success' => true, 'data' => $result]);
			} catch (Throwable $exception) {
				return response()->json(['success' => false, 'message' => $exception->getMessage()], 500);
			}
		}

		public function createBlank(Request $request): JsonResponse
		{
			try {
				$channel = 'books:createBlank';
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
					// MODIFIED: Using Eloquent UserBook::create to add new records [1]
					$book = UserBook::create([
						'user_id' => $userId,
						'title' => $data['title'],
						'source_language' => $data['source_language'],
						'target_language' => $data['target_language']
					]);
					$bookId = $book->id;

					// MODIFIED: Inserting default blank chapters through Chapter Eloquent model
					for ($i = 1; $i <= 10; $i++) {
						Chapter::create([
							'book_id' => $bookId,
							'title' => "Chapter $i",
							'chapter_order' => $i,
							'source_content' => '<p></p>',
							'target_content' => '<p></p>'
						]);
					}
					$result = ['success' => true, 'bookId' => $bookId];
					break;
				} while (false);

				return response()->json(['success' => true, 'data' => $result]);
			} catch (Throwable $exception) {
				return response()->json(['success' => false, 'message' => $exception->getMessage()], 500);
			}
		}

		public function updateMeta(Request $request): JsonResponse
		{
			try {
				$channel = 'books:updateMeta';
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
					// MODIFIED: Eloquent update replacement for metadata [1]
					UserBook::where('id', $data['bookId'])
						->where('user_id', $userId)
						->update([
							'title' => $data['title'],
							'author' => $data['author']
						]);
					$result = ['success' => true];
					break;
				} while (false);

				return response()->json(['success' => true, 'data' => $result]);
			} catch (Throwable $exception) {
				return response()->json(['success' => false, 'message' => $exception->getMessage()], 500);
			}
		}

		public function updateProseSettings(Request $request): JsonResponse
		{
			try {
				$channel = 'books:updateProseSettings';
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
					// MODIFIED: Eloquent update replacement for prose languages [1]
					UserBook::where('id', $data['bookId'])
						->where('user_id', $userId)
						->update([
							'source_language' => $data['source_language'],
							'target_language' => $data['target_language']
						]);
					$result = ['success' => true];
					break;
				} while (false);

				return response()->json(['success' => true, 'data' => $result]);
			} catch (Throwable $exception) {
				return response()->json(['success' => false, 'message' => $exception->getMessage()], 500);
			}
		}

		public function updatePromptSettings(Request $request): JsonResponse
		{
			try {
				$channel = 'books:updatePromptSettings';
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
					$allowedTypes = ['rephrase', 'translate'];
					if (!in_array($data['promptType'], $allowedTypes)) {
						throw new Exception('Invalid prompt type.');
					}
					$field = $data['promptType'] . '_settings';
					// MODIFIED: Clean Eloquent update with dynamic fields [1]
					UserBook::where('id', $data['bookId'])
						->where('user_id', $userId)
						->update([
							$field => json_encode($data['settings'])
						]);
					$result = ['success' => true];
					break;
				} while (false);

				return response()->json(['success' => true, 'data' => $result]);
			} catch (Throwable $exception) {
				return response()->json(['success' => false, 'message' => $exception->getMessage()], 500);
			}
		}

		public function updateCover(Request $request): JsonResponse
		{
			try {
				$channel = 'books:updateBookCover';
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
					$bookId = $data['bookId'];
					$coverInfo = $data['coverInfo'];
					$localPath = null;
					$imageType = 'unknown';

					if ($coverInfo['type'] === 'remote') {
						$paths = storeImageFromUrl($coverInfo['data'], $bookId, 'cover');
						$localPath = $paths['original_path'] ?? null;
						$imageType = 'generated';
					} elseif ($coverInfo['type'] === 'local') {
						$paths = storeImageFromPath($coverInfo['data'], $bookId, 'cover-upload');
						$localPath = $paths['original_path'] ?? null;
						$imageType = 'upload';
					} elseif ($coverInfo['type'] === 'existing') {
						$localPath = $coverInfo['data'];
						$imageType = 'generated';
					}

					if (!$localPath) {
						throw new Exception('Failed to store the new cover image.');
					}

					// MODIFIED: Fetch cover image using standard Eloquent Image model [1]
					$old = Image::where('book_id', $bookId)->first();
					if ($old && $old->image_local_path && $old->image_local_path !== $localPath) {
						@unlink(IMAGES_DIR . '/' . $old->image_local_path);
					}

					// MODIFIED: Standard Eloquent delete and recreate structure
					Image::where('book_id', $bookId)->delete();
					Image::create([
						'user_id' => $userId,
						'book_id' => $bookId,
						'image_local_path' => $localPath,
						'thumbnail_local_path' => $localPath,
						'image_type' => $imageType
					]);

					$result = ['success' => true, 'imagePath' => '/storage/userData/images/' . $localPath];
					break;
				} while (false);

				return response()->json(['success' => true, 'data' => $result]);
			} catch (Throwable $exception) {
				return response()->json(['success' => false, 'message' => $exception->getMessage()], 500);
			}
		}

		public function delete(Request $request): JsonResponse
		{
			try {
				$channel = 'books:delete';
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
					// MODIFIED: Fetch and clean up images via standard Eloquent logic [1]
					$images = Image::where('book_id', $bookId)->get();
					foreach ($images as $img) {
						@unlink(IMAGES_DIR . '/' . $img->image_local_path);
					}
					Image::where('book_id', $bookId)->delete();
					UserBook::where('id', $bookId)->where('user_id', $userId)->delete();

					$result = ['success' => true];
					break;
				} while (false);

				return response()->json(['success' => true, 'data' => $result]);
			} catch (Throwable $exception) {
				return response()->json(['success' => false, 'message' => $exception->getMessage()], 500);
			}
		}

		public function export(Request $request): JsonResponse
		{
			try {
				$channel = 'books:exportToDocx';
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
					$filename = preg_replace('/[^a-z0-9]/i', '_', $data['title']) . '_' . time() . '.doc';
					$filePath = DOWNLOADS_DIR . '/' . $filename;
					$html = "<html><head><meta charset='utf-8'></head><body>" . $data['htmlContent'] . "</body></html>";
					file_put_contents($filePath, $html);
					$result = ['success' => true, 'downloadUrl' => '/storage/userData/downloads/' . $filename, 'filename' => $filename];
					break;
				} while (false);

				return response()->json(['success' => true, 'data' => $result]);
			} catch (Throwable $exception) {
				return response()->json(['success' => false, 'message' => $exception->getMessage()], 500);
			}
		}

		public function highestMarker(Request $request): JsonResponse
		{
			try {
				$channel = 'books:findHighestMarkerNumber';
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
					$result = findHighestMarkerNumber($args[0], $args[1]);
					break;
				} while (false);

				return response()->json(['success' => true, 'data' => $result]);
			} catch (Throwable $exception) {
				return response()->json(['success' => false, 'message' => $exception->getMessage()], 500);
			}
		}
	}

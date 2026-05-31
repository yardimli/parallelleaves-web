<?php

	namespace App\Http\Controllers;

	use App\Models\Chapter;
	use App\Models\UserBook;
	use App\Support\PageData;
	use Illuminate\Http\RedirectResponse;
	use Illuminate\Support\Facades\Auth;
	use Illuminate\View\View;

	use function App\Http\Controllers\Api\countWordsInHtml;

	require_once __DIR__ . '/Api/ApiSupport.php';

	class PageController extends Controller
	{
// NEW: Specific method for index/landing page
		public function landing(): View
		{
			return view('pages.landing', PageData::viewData());
		}

// NEW: Specific method for login page
		public function login(): View|RedirectResponse
		{
			if (Auth::check()) {
				return redirect('/dashboard');
			}

			return view('pages.login', PageData::viewData());
		}

// NEW: Specific method for register page
		public function register(): View|RedirectResponse
		{
			if (Auth::check()) {
				return redirect('/dashboard');
			}

			return view('pages.register', PageData::viewData());
		}

// NEW: Specific method for splash page
		public function splash(): View
		{
			return view('pages.splash', PageData::viewData());
		}

// NEW: Specific method for dashboard page
		public function dashboard(): View
		{
			$userId = Auth::id();
			$books = UserBook::select('user_books.*', 'images.image_local_path as cover_path')
				->leftJoin('images', 'user_books.id', '=', 'images.book_id')
				->withCount('chapters as chapter_count')
				->where('user_books.user_id', $userId)
				->orderBy('user_books.updated_at', 'desc')
				->get()
				->map(function (UserBook $book) {
					$chapters = Chapter::select('source_content', 'target_content')
						->where('book_id', $book->id)
						->get();

					$bookData = $book->toArray();
					$bookData['source_word_count'] = $chapters->sum(fn(Chapter $chapter) => countWordsInHtml($chapter->source_content ?? ''));
					$bookData['target_word_count'] = $chapters->sum(fn(Chapter $chapter) => countWordsInHtml($chapter->target_content ?? ''));

					if (!empty($bookData['cover_path'])) {
						$bookData['cover_path'] = '/storage/userData/images/' . $bookData['cover_path'];
					}

					return $bookData;
				})
				->values();

			return view('pages.index', PageData::viewData(compact('books')));
		}

// NEW: Specific method for chapter editor page, passing parameters
		public function chapterEditor(string $bookId, ?string $chapterId = null): View
		{
			return view('pages.chapter-editor', PageData::viewData(compact('bookId', 'chapterId')));
		}

// NEW: Specific method for chat window page, passing parameters
		public function chatWindow(string $bookId): View
		{
			$book = UserBook::with('chapters')->where('id', $bookId)->where('user_id', Auth::id())->first();
			$chapters = $book?->chapters?->map(fn(Chapter $chapter) => [
				'id' => $chapter->id,
				'title' => $chapter->title,
				'source_content' => $chapter->source_content,
				'target_content' => $chapter->target_content,
			])->values() ?? collect();

			return view('pages.chat-window', PageData::viewData(compact('bookId', 'chapters')));
		}

// NEW: Specific method for codex editor page, passing parameters
		public function codexEditor(?string $bookId = null): View
		{
			return view('pages.codex-editor', PageData::viewData(compact('bookId')));
		}

// NEW: Specific method for editor iframe page
		public function editorIframe(): View
		{
			return view('pages.editor-iframe', PageData::viewData());
		}

// NEW: Specific method for import document page
		public function importDocument(): View
		{
			return view('pages.import-document', PageData::viewData());
		}

// NEW: Specific method for API logs page
		public function apiLogs(): View
		{
			return view('pages.api-logs', PageData::viewData());
		}

// NEW: Specific method for translation memory page, passing parameters
		public function translationMemory(?string $bookId = null): View
		{
			return view('pages.translation-memory', PageData::viewData(compact('bookId')));
		}
	}

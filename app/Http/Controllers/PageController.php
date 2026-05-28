<?php

	namespace App\Http\Controllers;

	use Illuminate\Http\RedirectResponse;
	use Illuminate\Support\Facades\Auth;
	use Illuminate\View\View;

	class PageController extends Controller
	{
// NEW: Specific method for login page
		public function login(): View|RedirectResponse
		{
			if (Auth::check()) {
				return redirect('/dashboard');
			}

			return view('pages.login');
		}

// NEW: Specific method for register page
		public function register(): View|RedirectResponse
		{
			if (Auth::check()) {
				return redirect('/dashboard');
			}

			return view('pages.register');
		}

// NEW: Specific method for splash page
		public function splash(): View
		{
			return view('pages.splash');
		}

// NEW: Specific method for dashboard page
		public function dashboard(): View
		{
			return view('pages.index');
		}

// NEW: Specific method for chapter editor page, passing parameters
		public function chapterEditor(string $bookId, ?string $chapterId = null): View
		{
			return view('pages.chapter-editor', compact('bookId', 'chapterId'));
		}

// NEW: Specific method for chat window page, passing parameters
		public function chatWindow(string $bookId): View
		{
			return view('pages.chat-window', compact('bookId'));
		}

// NEW: Specific method for codex editor page, passing parameters
		public function codexEditor(?string $bookId = null): View
		{
			return view('pages.codex-editor', compact('bookId'));
		}

// NEW: Specific method for editor iframe page
		public function editorIframe(): View
		{
			return view('pages.editor-iframe');
		}

// NEW: Specific method for import document page
		public function importDocument(): View
		{
			return view('pages.import-document');
		}

// NEW: Specific method for API logs page
		public function apiLogs(): View
		{
			return view('pages.api-logs');
		}

// NEW: Specific method for translation memory page, passing parameters
		public function translationMemory(?string $bookId = null): View
		{
			return view('pages.translation-memory', compact('bookId'));
		}
	}

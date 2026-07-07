<?php

	namespace App\Http\Controllers;

	use App\Models\User;
	use App\Support\PageData;
	use Illuminate\Http\RedirectResponse;
	use Illuminate\Http\Request;
	use Illuminate\Support\Facades\Auth;
	use Illuminate\View\View;

	class AdminController extends Controller
	{
		public function index(): View
		{
			$this->authorizeAdmin();

			$users = User::query()
				->select(['id', 'username', 'email', 'google_id', 'is_admin', 'created_at'])
				->orderByDesc('is_admin')
				->orderBy('username')
				->get();

			return view('pages.admin', PageData::viewData(compact('users')));
		}

		public function loginAs(Request $request, User $user): RedirectResponse
		{
			$admin = $this->authorizeAdmin();

			if (!$admin->is($user) && !$request->session()->has('admin_impersonator_id')) {
				$request->session()->put('admin_impersonator_id', $admin->id);
			}

			Auth::login($user);
			$request->session()->regenerate();

			return redirect('/dashboard');
		}

		public function stopImpersonating(Request $request): RedirectResponse
		{
			$adminId = $request->session()->get('admin_impersonator_id');
			abort_unless($adminId, 403);

			$admin = User::find($adminId);
			abort_unless($admin && $admin->is_admin, 403);

			Auth::login($admin);
			$request->session()->forget('admin_impersonator_id');
			$request->session()->regenerate();

			return redirect('/admin');
		}

		private function authorizeAdmin(): User
		{
			$user = Auth::user();
			abort_unless($user && $user->is_admin, 403);

			return $user;
		}
	}

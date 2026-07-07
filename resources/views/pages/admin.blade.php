<!DOCTYPE html>
<html lang="{{ $selectedLang ?? 'en' }}">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Admin - Parallel Leaves</title>
	<link rel="stylesheet" href="{{ asset('vendor/bootstrap-icons/bootstrap-icons.css') }}">
	<link rel="stylesheet" href="/dist/styles.css">
</head>
<body>
<div class="container mx-auto p-8">
	<div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between mb-8">
		<div>
			<h1 class="text-2xl font-bold">Admin</h1>
			<p class="text-sm text-base-content/70">Manage users and switch into a user session.</p>
		</div>
		<div class="flex items-center gap-2">
			<a href="/dashboard" class="btn btn-ghost btn-sm">
				<i class="bi bi-house"></i>
				<span>Dashboard</span>
			</a>
		</div>
	</div>

	<div class="overflow-x-auto bg-base-200 rounded-lg shadow">
		<table class="table">
			<thead>
			<tr>
				<th>User</th>
				<th>Email</th>
				<th>Login Type</th>
				<th>Role</th>
				<th>Created</th>
				<th class="text-right">Actions</th>
			</tr>
			</thead>
			<tbody>
			@foreach($users as $user)
				<tr>
					<td>
						<div class="font-medium">{{ $user->username }}</div>
						<div class="text-xs text-base-content/60">#{{ $user->id }}</div>
					</td>
					<td>{{ $user->email ?: '-' }}</td>
					<td>
						<span class="badge badge-outline">{{ $user->google_id ? 'Google' : 'Password' }}</span>
					</td>
					<td>
						@if($user->is_admin)
							<span class="badge badge-primary">Admin</span>
						@else
							<span class="badge badge-ghost">User</span>
						@endif
					</td>
					<td>{{ $user->created_at ? $user->created_at->format('M j, Y') : '-' }}</td>
					<td class="text-right">
						<form method="POST" action="/admin/users/{{ $user->id }}/login-as" class="inline-block">
							@csrf
							<button class="btn btn-sm btn-outline" type="submit">
								<i class="bi bi-box-arrow-in-right"></i>
								<span>Login as</span>
							</button>
						</form>
					</td>
				</tr>
			@endforeach
			</tbody>
		</table>
	</div>
</div>
</body>
</html>

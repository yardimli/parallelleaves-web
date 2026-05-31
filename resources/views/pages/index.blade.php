<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title id="app-title">Parallel Leaves</title>
	<link rel="stylesheet" href="{{ asset('vendor/bootstrap-icons/bootstrap-icons.css') }}">
	<link rel="stylesheet" href="/dist/styles.css">
</head>
<body>
<div class="container mx-auto p-8">
	<div class="flex justify-between items-center mb-8">
		<h1 class="text-2xl font-bold" data-i18n="dashboard.title">My Translation Projects</h1>
		
		<div class="flex items-center gap-2">
			<button id="new-project-btn-menu" type="button" class="btn btn-primary btn-sm">
				<i class="bi bi-file-earmark-plus"></i>
				<span data-i18n="dashboard.newProject">New Blank Project</span>
			</button>
			<button id="import-doc-btn-menu" type="button" class="btn btn-outline btn-sm">
				<i class="bi bi-file-earmark-arrow-up"></i>
				<span data-i18n="dashboard.importDocument">Import Document</span>
			</button>
			
			<!-- Refresh Page Button -->
			<button id="js-refresh-page-btn" class="btn btn-ghost btn-circle" data-i18n-title="common.refresh">
				<i class="bi bi-arrow-clockwise text-2xl"></i>
			</button>
			
			<!-- Theme Toggle Button -->
			<button id="theme-toggle" class="btn btn-ghost btn-circle" data-i18n-title="common.changeTheme">
				<i class="bi bi-sun-fill text-2xl"></i>
			</button>
			
			<!-- Hamburger Menu Dropdown -->
			<div class="dropdown dropdown-end">
				<button tabindex="0" role="button" class="btn btn-ghost btn-circle">
					<i class="bi bi-list text-3xl"></i>
				</button>
				<ul tabindex="0" class="dropdown-content z-[1] menu p-2 shadow bg-base-200 rounded-box w-56 mt-4">
					<!-- Auth section is populated by JS -->
					<div id="auth-menu-section"></div>
					
					<!-- Divider is shown/hidden by JS -->
					<div class="divider my-1" id="auth-divider"></div>
					
					<!-- NEW DASHBOARD LINKS -->
					<!-- MODIFIED: Removed Translation Memory and Codex Editor from the main menu -->
					<li>
						<a href="/api-logs">
							<i class="bi bi-terminal-fill"></i>
							<span data-i18n="">API Logs</span>
						</a>
					</li>
					
					<div class="divider my-1"></div>
					
					<!-- Language Switcher Submenu -->
					<li>
						<details>
							<summary>
								<i class="bi bi-translate"></i>
								<span data-i18n="common.changeLanguage">Change Language</span>
							</summary>
							<ul id="js-lang-switcher-menu" class="p-2 bg-base-300">
								<!-- Populated by i18n.js -->
							</ul>
						</details>
					</li>
				</ul>
			</div>
		</div>
	</div>
	
	<div id="book-list" class="flex flex-col gap-6">
		@forelse($books ?? [] as $book)
			@php
				$sourceWords = (int) ($book['source_word_count'] ?? 0);
				$targetWords = (int) ($book['target_word_count'] ?? 0);
				$progress = $sourceWords > 0 ? round(($targetWords / $sourceWords) * 100) : ($targetWords > 0 ? 100 : 0);
				$progress = min(100, max(0, $progress));
				$updatedAt = $book['updated_at'] ?? null;
				$coverPath = $book['cover_path'] ?? null;
				$coverSrc = $coverPath
					? $coverPath . ($updatedAt ? '?t=' . strtotime($updatedAt) : '')
					: './assets/bookcover-placeholder.jpg';
			@endphp
			<div class="card bg-base-200 shadow-xl transition-shadow h-full flex card-side flex-row" data-book-id="{{ $book['id'] }}">
				<figure class="cursor-pointer js-open-editor max-w-[200px]">
					<img src="{{ $coverSrc }}" alt="{{ $coverPath ? 'Cover for ' . $book['title'] : 'No Cover' }}" class="w-full {{ $coverPath ? '' : 'h-auto' }}">
				</figure>
				<div class="card-body flex flex-col flex-grow">
					<h2 class="card-title js-open-editor cursor-pointer">{{ $book['title'] }}</h2>
					<p class="text-base-content/80 -mt-2 mb-2">{{ $book['author'] ?: 'Unknown Author' }}</p>
					
					<div class="text-xs space-y-2 text-base-content/70 mt-auto">
						<div>
							<div class="flex justify-between mb-1">
								<span class="font-semibold" data-i18n="dashboard.card.progress">Progress</span>
								<span class="js-progress-percent">{{ $progress }}%</span>
							</div>
							<progress class="progress progress-primary w-full js-progress-bar" value="{{ $progress }}" max="100"></progress>
						</div>
						
						<div class="grid grid-cols-5 gap-x-4">
							<div>
								<div class="font-semibold" data-i18n="dashboard.card.sourceWords">Source</div>
								<div class="js-source-words">{{ number_format($sourceWords) }} <span data-i18n="common.words">words</span></div>
							</div>
							<div>
								<div class="font-semibold" data-i18n="dashboard.card.targetWords">Target</div>
								<div class="js-target-words">{{ number_format($targetWords) }} <span data-i18n="common.words">words</span></div>
							</div>
							<div>
								<div class="font-semibold" data-i18n="dashboard.card.chapters">Chapters</div>
								<div class="js-chapter-count">{{ $book['chapter_count'] ?? 0 }}</div>
							</div>
							<div>
								<div class="font-semibold" data-i18n="dashboard.card.created">Created:</div>
								<div class="js-created-date">{{ !empty($book['created_at']) ? \Carbon\Carbon::parse($book['created_at'])->format('M j, Y') : '' }}</div>
							</div>
							<div>
								<div class="font-semibold" data-i18n="dashboard.card.updated">Updated:</div>
								<div class="js-updated-date">{{ !empty($book['updated_at']) ? \Carbon\Carbon::parse($book['updated_at'])->format('M j, Y') : '' }}</div>
							</div>
						</div>
					</div>
					
					<div class="card-actions start items-center mt-4">
						<button class="btn btn-primary btn-sm js-open-editor" data-i18n-title="dashboard.card.openEditor">
							<i class="bi bi-pencil"></i>
							<span data-i18n="dashboard.card.openEditor">Open Editor</span>
						</button>
						<button class="btn btn-ghost btn-sm js-meta-settings" data-i18n-title="common.edit">
							<i class="bi bi-pencil-square text-lg"></i>
						</button>
						<button class="btn btn-ghost btn-sm js-export-docx" data-i18n-title="export.exportDocx">
							<i class="bi bi-file-earmark-word text-lg"></i>
						</button>
					</div>
				</div>
			</div>
		@empty
			<p class="text-base-content/70 text-center" data-i18n="dashboard.noProjects">You haven't started any translation projects yet.</p>
		@endforelse
	</div>
</div>

<!-- MODIFIED: Removed the prose-settings-modal since language settings are now Consolidated into Edit Project Details -->

<dialog id="meta-settings-modal" class="modal">
	<!-- MODIFIED: Setup standard flexible column model with h-[90vh] and p-0 padding -->
	<div class="modal-box w-11/12 max-w-3xl h-[90vh] flex flex-col p-0">
		
		<!-- MODIFIED: Fixed Top Header container featuring bottom border division -->
		<div class="flex-shrink-0 p-6 pb-4 border-b border-base-300 relative">
			<form method="dialog">
				<button class="btn btn-sm btn-circle btn-ghost absolute right-2 top-2">✕</button>
			</form>
			<h3 class="font-bold text-lg" data-i18n="dashboard.metaSettings.title">Edit Project Details</h3>
		</div>
		
		<!-- MODIFIED: Scrollable Body block with overflow-y-auto to handle form elements internally -->
		<div class="flex-grow overflow-y-auto min-h-0 p-6 space-y-6">
			<div class="grid grid-cols-1 md:grid-cols-12 gap-6">
				<div class="space-y-4 md:col-span-7">
					<form id="meta-settings-form">
						<input type="hidden" id="meta-book-id" name="bookId">
						
						<!-- Title -->
						<div class="form-control">
							<label for="meta-title" class="label"><span class="label-text"
							                                            data-i18n="dashboard.metaSettings.projectTitle">Title</span></label>
							<input type="text" id="meta-title" name="title" class="input input-bordered" required>
						</div>
						
						<!-- Author -->
						<div class="form-control">
							<label for="meta-author" class="label"><span class="label-text" data-i18n="dashboard.metaSettings.author">Author</span></label>
							<input type="text" id="meta-author" name="author" class="input input-bordered" required>
						</div>
						
						<!-- MODIFIED: Consolidated Language Form fields inside this modal -->
						<div class="form-control">
							<label for="prose_source_language" class="label"><span class="label-text"
							                                                       data-i18n="dashboard.proseSettings.sourceLanguage">Source Language</span></label>
							<select id="prose_source_language" name="prose_source_language" class="select select-bordered">
								<!-- Options populated by JS -->
							</select>
							<div class="label">
								<span class="label-text-alt" data-i18n="dashboard.proseSettings.sourceLanguageHelp">The original language of the document.</span>
							</div>
						</div>
						
						<div class="form-control">
							<label for="prose_target_language" class="label"><span class="label-text"
							                                                       data-i18n="dashboard.proseSettings.targetLanguage">Target Language</span></label>
							<select id="prose_target_language" name="prose_target_language" class="select select-bordered">
								<!-- Options populated by JS -->
							</select>
							<div class="label">
								<span class="label-text-alt" data-i18n="dashboard.proseSettings.targetLanguageHelp">The language to translate into. Used for spell checking and AI generation.</span>
							</div>
						</div>
					</form>
					
					<!-- AI Generation controls (hidden by default) -->
					<div id="meta-ai-gen-controls" class="hidden space-y-2">
						<div class="form-control">
							<label for="meta-ai-prompt" class="label"><span class="label-text"
							                                                data-i18n="dashboard.generateCover.prompt">Image Prompt</span></label>
							<textarea id="meta-ai-prompt" class="textarea textarea-bordered h-24"
							          placeholder="A lone astronaut on a red planet..."></textarea>
						</div>
						<div class="flex gap-2 justify-start">
							<button id="cancel-generate-cover-btn" type="button" class="btn btn-ghost" data-i18n="common.cancel">
								Cancel
							</button>
							<button id="run-generate-cover-btn" type="button" class="btn btn-accent w-40">
								<span class="js-btn-content flex items-center gap-2"><i class="bi bi-stars"></i> <span
										data-i18n="dashboard.generateCover.generate">Generate</span></span>
								<span class="js-btn-spinner animate-spin hidden"><i class="bi bi-arrow-repeat"></i></span>
							</button>
						</div>
					</div>
				</div>
				
				<!-- Right Column: Cover Art (2/5 width) -->
				<div class="space-y-4 md:col-span-5 relative">
					<div id="meta-cover-actions" class="absolute top-2 left-2 z-10 flex gap-2">
						<button id="generate-cover-btn" class="btn btn-neutral btn-square tooltip" data-tip="Generate AI Cover"
						        data-i18n-title="dashboard.metaSettings.generateCover">
							<i class="bi bi-magic text-xl"></i>
						</button>
						<button id="upload-cover-btn" class="btn btn-neutral btn-square tooltip" data-tip="Upload Cover"
						        data-i18n-title="dashboard.metaSettings.uploadCover">
							<i class="bi bi-upload text-xl"></i>
						</button>
					</div>
					
					<div id="meta-cover-preview"
					     class="rounded-lg overflow-hidden w-full mx-auto bg-base-300 flex items-center justify-center min-h-48 text-base-content/50">
						<!-- Preview image will be inserted here by JS -->
						<span data-i18n="dashboard.metaSettings.noCover">No new cover selected</span>
					</div>
				</div>
			</div>
			
			<!-- Danger Zone (Full Width) -->
			<div class="space-y-4">
				<div class="divider" data-i18n="dashboard.metaSettings.dangerZone">Danger Zone</div>
				<div class="flex justify-between items-center bg-error/10 p-4 rounded-lg">
					<p class="text-sm" data-i18n="dashboard.metaSettings.dangerZoneDesc">Permanently delete this project and all
						its content.</p>
					<button id="delete-book-btn" class="btn btn-error" data-i18n="dashboard.metaSettings.deleteProject">Delete
						Project
					</button>
				</div>
			</div>
		</div>
		
		<!-- MODIFIED: Fixed Bottom Footer container featuring border division and responsive submit flex action -->
		<div class="flex-shrink-0 px-6 py-4 border-t border-base-300">
			<div class="modal-action p-0 mt-0">
				<form method="dialog" class="flex gap-3 w-full">
					<button class="btn flex-1" data-i18n="common.cancel">Cancel</button>
					<button id="save-meta-settings-btn" class="btn btn-primary flex-1"
					        data-i18n="dashboard.metaSettings.saveButton">Save Changes
					</button>
				</form>
			</div>
		</div>
	</div>
</dialog>

<!-- NEW MODAL: For creating a new blank project -->
<dialog id="new-project-modal" class="modal">
	<div class="modal-box w-11/12 max-w-lg">
		<h3 class="font-bold text-lg" data-i18n="dashboard.newProjectModal.title">Create New Blank Project</h3>
		<p class="py-4 text-sm text-base-content/70" data-i18n="dashboard.newProjectModal.description">Provide a title and
			languages for your new project. It will be created with a default structure of 3 acts and 10 chapters per act.</p>
		<form id="new-project-form" class="space-y-4">
			<div class="form-control">
				<label for="new-project-title" class="label"><span class="label-text"
				                                                   data-i18n="dashboard.newProjectModal.projectTitle">Project Title</span></label>
				<input type="text" id="new-project-title" name="title" class="input input-bordered" required>
			</div>
			<div class="form-control">
				<label for="new-project-source-language" class="label"><span class="label-text"
				                                                             data-i18n="dashboard.newProjectModal.sourceLanguage">Source Language</span></label>
				<select id="new-project-source-language" name="source_language" class="select select-bordered" required>
					<!-- Options populated by JS -->
				</select>
			</div>
			<div class="form-control">
				<label for="new-project-target-language" class="label"><span class="label-text"
				                                                             data-i18n="dashboard.newProjectModal.targetLanguage">Target Language</span></label>
				<select id="new-project-target-language" name="target_language" class="select select-bordered" required>
					<!-- Options populated by JS -->
				</select>
			</div>
		</form>
		<div class="modal-action">
			<form method="dialog" class="flex gap-3 w-full">
				<button class="btn flex-1" data-i18n="common.cancel">Cancel</button>
				<button id="create-project-btn" type="submit" form="new-project-form" class="btn btn-primary flex-1"
				        data-i18n="dashboard.newProjectModal.createButton">Create Project
				</button>
			</form>
		</div>
	</div>
</dialog>

<dialog id="alert-modal" class="modal">
	<div class="modal-box w-11/12 max-w-md">
		<h3 id="alert-modal-title" class="font-bold text-lg text-error" data-i18n="common.error">Error</h3>
		<p id="alert-modal-content" class="py-4"></p>
		<div class="modal-action">
			<form method="dialog">
				<button class="btn" data-i18n="common.close">Close</button>
			</form>
		</div>
	</div>
</dialog>

<dialog id="update-modal" class="modal">
	<div class="modal-box w-11/12 max-w-md">
		<h3 id="update-modal-title" class="font-bold text-lg" data-i18n="dashboard.update.title">Update Available</h3>
		<p id="update-modal-content" class="py-4"></p>
		<div class="modal-action">
			<form method="dialog" class="flex gap-3 w-full">
				<button class="btn flex-1" data-i18n="common.close">Later</button>
				<a id="update-modal-link" href="#" class="btn btn-primary flex-1" data-i18n="dashboard.update.updateNow">Update
					Now</a>
			</form>
		</div>
	</div>
</dialog>

@php echo file_get_contents(resource_path('legacy/templates/modals/input-modal.blade.php')); @endphp

<script>
	window.dashboardBooks = @json($books ?? []);
</script>
<script src="/js/api.js"></script>
<script src="/src/js/theme.js"></script>
<script type="module" src="/src/js/dashboard.js"></script>
</body>
</html>

<!DOCTYPE html>
<html lang="{{ $selectedLang ?? 'en' }}" class="h-full">
<head>
	<meta charset="UTF-8"/>
	<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no"/>
	<title>{{ $tr('editor.translating', 'Parallel Leaves - Chapter Editor') }}</title>
	<link rel="stylesheet" href="{{ asset('vendor/bootstrap-icons/bootstrap-icons.css') }}">
	<link rel="stylesheet" href="/dist/styles.css">
</head>
<body class="h-full bg-base-100 text-base-content overflow-hidden flex flex-col">

<!-- Top Toolbar (copied from book-editor.php for consistent editing experience) -->
<div id="top-toolbar"
     class="flex-shrink-0 h-12 bg-base-100/80 dark:bg-base-300/80 backdrop-blur-sm flex items-center px-4 gap-2 z-50 border-b border-base-300 dark:border-base-100/10">
	<!-- History Section -->
	<div class="flex items-center gap-1">
		<button type="button" class="js-toolbar-btn btn btn-ghost btn-sm btn-square" data-command="undo"
		        title="{{ $tr('editor.undo', '') }}" disabled>
			<i class="bi bi-arrow-counterclockwise text-lg"></i>
		</button>
		<button type="button" class="js-toolbar-btn btn btn-ghost btn-sm btn-square" data-command="redo"
		        title="{{ $tr('editor.redo', '') }}" disabled>
			<i class="bi bi-arrow-clockwise text-lg"></i>
		</button>
	</div>
	<div class="divider divider-horizontal mx-0"></div>
	<!-- Heading Dropdown -->
	<div class="dropdown js-dropdown-container">
		<button type="button" tabindex="0" role="button"
		        class="js-toolbar-btn js-heading-btn btn btn-ghost btn-sm w-28 justify-start" disabled
		>{{ $tr('editor.paragraph', 'Paragraph') }}</button>
		<div tabindex="0" class="js-dropdown dropdown-content z-[1] menu p-1 shadow bg-base-100 rounded-box w-40">
			<button class="js-heading-option p-2 rounded w-full text-left text-sm hover:bg-base-200" data-level="0"
			>{{ $tr('editor.paragraph', 'Paragraph') }}</button>
			<button class="js-heading-option p-2 rounded w-full text-left text-xl font-bold hover:bg-base-200"
			        data-level="1">{{ $tr('editor.heading1', 'Heading 1') }}</button>
			<button class="js-heading-option p-2 rounded w-full text-left text-lg font-bold hover:bg-base-200"
			        data-level="2">{{ $tr('editor.heading2', 'Heading 2') }}</button>
			<button class="js-heading-option p-2 rounded w-full text-left text-base font-bold hover:bg-base-200"
			        data-level="3">{{ $tr('editor.heading3', 'Heading 3') }}</button>
			<button class="js-heading-option p-2 rounded w-full text-left text-sm font-bold hover:bg-base-200"
			        data-level="4">{{ $tr('editor.heading4', 'Heading 4') }}</button>
		</div>
	</div>
	<div class="divider divider-horizontal mx-0"></div>
	<!-- Formatting Section -->
	<div class="flex items-center gap-1">
		<button type="button" class="js-toolbar-btn btn btn-ghost btn-sm btn-square font-bold" data-command="bold"
		        title="{{ $tr('editor.bold', '') }}" disabled>B
		</button>
		<button type="button" class="js-toolbar-btn btn btn-ghost btn-sm btn-square italic" data-command="italic"
		        title="{{ $tr('editor.italic', '') }}" disabled>I
		</button>
		<button type="button" class="js-toolbar-btn btn btn-ghost btn-sm btn-square underline"
		        data-command="underline"
		        title="{{ $tr('editor.underline', '') }}" disabled>U
		</button>
		<button type="button" class="js-toolbar-btn btn btn-ghost btn-sm btn-square line-through"
		        data-command="strike"
		        title="{{ $tr('editor.strikethrough', '') }}" disabled>S
		</button>
		<div class="dropdown js-dropdown-container">
			<button type="button" tabindex="0" role="button" class="js-toolbar-btn btn btn-ghost btn-sm btn-square"
			        title="{{ $tr('editor.highlight', '') }}" disabled>
				<i class="bi bi-highlighter text-lg"></i>
			</button>
			<div tabindex="0" class="js-dropdown dropdown-content z-[1] menu p-1 shadow bg-base-100 rounded-box w-32">
				<button
					class="js-highlight-option p-1 rounded w-full text-left flex items-center gap-2 text-xs hover:bg-base-200"
					data-bg="highlight-yellow"><span class="w-4 h-4 rounded-full"
				                                   style="background-color: #fef08a;"></span>
					<span>{{ $tr('editor.yellow', 'Yellow') }}</span>
				</button>
				<button
					class="js-highlight-option p-1 rounded w-full text-left flex items-center gap-2 text-xs hover:bg-base-200"
					data-bg="highlight-green"><span class="w-4 h-4 rounded-full"
				                                  style="background-color: #a7f3d0;"></span>
					<span>{{ $tr('editor.green', 'Green') }}</span>
				</button>
				<button
					class="js-highlight-option p-1 rounded w-full text-left flex items-center gap-2 text-xs hover:bg-base-200"
					data-bg="highlight-blue"><span class="w-4 h-4 rounded-full"
				                                 style="background-color: #bfdbfe;"></span> <span
					>{{ $tr('editor.blue', 'Blue') }}</span>
				</button>
				<button
					class="js-highlight-option p-1 rounded w-full text-left flex items-center gap-2 text-xs hover:bg-base-200"
					data-bg="highlight-red"><span class="w-4 h-4 rounded-full"
				                                style="background-color: #fecaca;"></span> <span
					>{{ $tr('editor.red', 'Red') }}</span>
				</button>
				<button class="js-highlight-option p-1 rounded w-full text-left text-xs hover:bg-base-200"
				        data-bg="transparent">{{ $tr('editor.none', 'None') }}</button>
			</div>
		</div>
	</div>
	<div class="divider divider-horizontal mx-0"></div>
	<!-- Block Formatting Section -->
	<div class="flex items-center gap-1">
		<button type="button" class="js-toolbar-btn btn btn-ghost btn-sm btn-square" data-command="bullet_list"
		        title="{{ $tr('editor.bulletList', '') }}" disabled><i class="bi bi-list-ul text-lg"></i></button>
		<button type="button" class="js-toolbar-btn btn btn-ghost btn-sm btn-square" data-command="ordered_list"
		        title="{{ $tr('editor.orderedList', '') }}" disabled><i class="bi bi-list-ol text-lg"></i></button>
		<button type="button" class="js-toolbar-btn btn btn-ghost btn-sm btn-square" data-command="blockquote"
		        title="{{ $tr('editor.blockquote', '') }}" disabled><i class="bi bi-quote text-lg"></i></button>
		<button type="button" class="js-toolbar-btn btn btn-ghost btn-sm btn-square" data-command="horizontal_rule"
		        title="{{ $tr('editor.horizontalRule', '') }}" disabled><i class="bi bi-hr text-lg"></i></button>
	</div>
	<div class="divider divider-horizontal mx-0"></div>
	<div class="flex items-center gap-1">
		<button type="button" class="js-toolbar-btn js-ai-action-btn btn btn-ghost btn-sm" data-action="rephrase"
		        title="{{ $tr('editor.rephrase', '') }}" disabled>{{ $tr('editor.rephrase', 'Rephrase') }}</button>
	</div>
	<div class="divider divider-horizontal mx-0"></div>
	<div class="flex items-center gap-1">
		<button type="button" id="js-open-chat-btn" class="btn btn-ghost btn-sm btn-square"
		        title="{{ $tr('editor.openChat', '') }}">
			<i class="bi bi-chat-dots text-lg"></i>
		</button>
		<button type="button" id="js-google-search-btn" class="btn btn-ghost btn-sm btn-square"
		        title="{{ $tr('editor.googleSearch', 'Search selected text on Google') }}" disabled>
			<i class="bi bi-google text-lg"></i>
		</button>
	</div>
	<div class="divider divider-horizontal mx-0"></div>
	<div class="flex items-center gap-1">
		<button type="button" id="js-search-btn" class="btn btn-ghost btn-sm btn-square"
		        title="{{ $tr('editor.search', '') }}">
			<i class="bi bi-search text-lg"></i>
		</button>
		<button type="button" id="js-search-replace-btn" class="btn btn-ghost btn-sm btn-square"
		        title="{{ $tr('editor.searchReplace.title', '') }}">
			<i class="bi bi-search-heart text-lg"></i>
		</button>
	</div>
	<div class="divider divider-horizontal mx-0"></div>
	<div class="flex items-center gap-1">
		<button type="button" id="js-open-dictionary-btn" class="btn btn-ghost btn-sm btn-square"
		        title="{{ $tr('prompt.common.dictionary', '') }}">
			<i class="bi bi-book text-lg"></i>
		</button>
		<!-- MODIFIED: Added Codex Editor Link opening in a new tab -->
		<a id="js-toolbar-codex-btn" href="#" target="_blank" class="btn btn-ghost btn-sm btn-square"
		   title="{{ $tr('editor.codex.openEditor', 'Codex Editor') }}">
			<i class="bi bi-journal-bookmark-fill text-lg"></i>
		</a>
		<!-- MODIFIED: Added Translation Memory Link opening in a new tab -->
		<a id="js-toolbar-tm-btn" href="#" target="_blank" class="btn btn-ghost btn-sm btn-square"
		   title="{{ $tr('editor.translationMemory.openEditor', 'Translation Memory') }}">
			<i class="bi bi-book-fill text-lg"></i>
		</a>
	</div>
	<div class="flex-grow"></div>
	<button id="typography-settings-btn" class="btn btn-ghost btn-circle" title="{{ $tr('common.typography', '') }}">
		<i class="bi bi-fonts text-xl"></i>
	</button>
	<div class="dropdown dropdown-end">
		<button tabindex="0" role="button" class="btn btn-ghost btn-circle" title="{{ $tr('common.changeLanguage', '') }}">
			<i class="bi bi-translate text-xl"></i>
		</button>
		<ul tabindex="0" id="js-lang-switcher-menu"
		    class="dropdown-content z-[1] menu p-2 shadow bg-base-200 rounded-box w-32">
			<!-- Populated by i18n.js -->
		</ul>
	</div>
	<!-- Refresh Page Button -->
	<button id="js-refresh-page-btn" class="btn btn-ghost btn-circle" title="{{ $tr('common.refresh', '') }}">
		<i class="bi bi-arrow-clockwise text-xl"></i>
	</button>
	<button id="theme-toggle" class="btn btn-ghost btn-circle" title="{{ $tr('common.changeTheme', '') }}">
		<i class="bi bi-sun-fill text-xl"></i>
	</button>
	<!-- MODIFIED: Home Button taking the user to the dashboard, positioned at the absolute right -->
	<a href="/dashboard" class="btn btn-ghost btn-circle mr-2" title="{{ $tr('common.home', 'Home') }}">
		<i class="bi bi-house-door text-xl"></i>
	</a>
</div>

<!-- Search Bar -->
<div id="js-search-bar"
     class="hidden flex-shrink-0 h-12 bg-base-200 flex items-center px-4 gap-4 z-40 border-b border-base-300">
	<input type="text" id="js-search-input" class="input input-sm input-bordered w-full max-w-xs"
	       placeholder="{{ $tr('editor.searchBar.placeholder', '') }}">
	<div class="form-control">
		<label class="label cursor-pointer gap-2 p-0">
			<span class="label-text text-sm">{{ $tr('editor.searchBar.source', 'Source') }}</span>
			<input type="radio" name="search-scope" class="radio radio-sm" value="source" checked/>
		</label>
	</div>
	<div class="form-control">
		<label class="label cursor-pointer gap-2 p-0">
			<span class="label-text text-sm">{{ $tr('editor.searchBar.target', 'Target') }}</span>
			<input type="radio" name="search-scope" class="radio radio-sm" value="target"/>
		</label>
	</div>
	<div class="flex items-center gap-1">
		<button id="js-search-prev-btn" class="btn btn-ghost btn-sm btn-square"
		        title="{{ $tr('editor.searchBar.previous', '') }}"
		        disabled>
			<i class="bi bi-chevron-up"></i>
		</button>
		<button id="js-search-next-btn" class="btn btn-ghost btn-sm btn-square"
		        title="{{ $tr('editor.searchBar.next', '') }}"
		        disabled>
			<i class="bi bi-chevron-down"></i>
		</button>
	</div>
	<span id="js-search-results-count" class="text-sm text-base-content/70 w-24 text-center"></span>
	<div class="flex-grow"></div>
	<button id="js-search-close-btn" class="btn btn-ghost btn-sm btn-square"
	        title="{{ $tr('editor.searchBar.close', '') }}">
		<i class="bi bi-x-lg"></i>
	</button>
</div>

<!-- Search and Replace Bar -->
<div id="js-search-replace-bar"
     class="hidden flex-shrink-0 h-auto bg-base-200 flex flex-col p-2 gap-1 z-40 border-b border-base-300">
	<!-- Find Row -->
	<div class="flex items-center gap-2">
		<!-- Input container with 50% width -->
		<div class="relative w-1/2">
<span class="absolute inset-y-0 left-0 flex items-center pl-3 text-base-content/50 pointer-events-none">
<i class="bi bi-search"></i>
</span>
			<input type="text" id="js-search-replace-input" class="input input-sm input-bordered w-full pl-10 pr-12"
			       placeholder="{{ $tr('editor.searchReplace.findPlaceholder', '') }}">
			<button id="js-case-sensitive-btn"
			        class="absolute inset-y-0 right-0 flex items-center px-2 btn btn-ghost btn-sm font-mono"
			        title="{{ $tr('editor.searchReplace.caseSensitive', '') }}">
				Aa
			</button>
		</div>
		<!-- Buttons and info aligned left -->
		<span id="js-search-replace-results-count" class="text-sm text-base-content/70 w-20 text-center"></span>
		<div class="flex items-center gap-1">
			<button id="js-search-replace-prev-btn" class="btn btn-ghost btn-sm btn-square"
			        title="{{ $tr('editor.searchReplace.previous', '') }}" disabled>
				<i class="bi bi-chevron-up"></i>
			</button>
			<button id="js-search-replace-next-btn" class="btn btn-ghost btn-sm btn-square"
			        title="{{ $tr('editor.searchReplace.next', '') }}" disabled>
				<i class="bi bi-chevron-down"></i>
			</button>
		</div>
		<!-- Spacer to push close button to the right -->
		<div class="flex-grow"></div>
		<button id="js-search-replace-close-btn" class="btn btn-ghost btn-sm btn-square"
		        title="{{ $tr('editor.searchReplace.close', '') }}">
			<i class="bi bi-x-lg"></i>
		</button>
	</div>
	<!-- Replace Row -->
	<div class="flex items-center gap-2">
		<!-- Input container with 50% width -->
		<div class="relative w-1/2">
<span class="absolute inset-y-0 left-0 flex items-center pl-3 text-base-content/50 pointer-events-none">
<i class="bi bi-arrow-left-right"></i>
</span>
			<input type="text" id="js-replace-input" class="input input-sm input-bordered w-full pl-10"
			       placeholder="{{ $tr('editor.searchReplace.replacePlaceholder', '') }}">
		</div>
		<!-- Buttons aligned left -->
		<div class="flex items-center gap-1">
			<button id="js-replace-btn" class="btn btn-sm btn-outline" disabled>
				{{ $tr('editor.searchReplace.replace', 'Replace') }}
			</button>
			<button id="js-replace-all-btn" class="btn btn-sm btn-outline"
			        disabled>{{ $tr('editor.searchReplace.replaceAll', 'Replace All') }}
			</button>
		</div>
	</div>
</div>


<!-- Main Content Area -->
<div class="flex-grow flex flex-col overflow-hidden">
	<div class="flex-shrink-0 flex items-center gap-4 px-4 py-2 border-b border-base-300">
		<h2 id="js-book-title" class="text-xl font-bold">{{ $tr('common.loading', 'Loading...') }}</h2>
		<select id="js-chapter-nav-dropdown" class="select select-bordered select-sm w-full max-w-xs">
			<option>{{ $tr('editor.loadingNav', 'Loading navigation...') }}</option>
		</select>
		<div class="flex-grow"></div>
		<div class="flex items-center gap-2">
			<label for="js-spellcheck-lang-dropdown" class="text-sm font-medium"
			>{{ $tr('editor.spellcheck', 'Spellcheck:') }}</label>
			<select id="js-spellcheck-lang-dropdown" class="select select-bordered select-sm">
				<option>{{ $tr('common.loading', 'Loading...') }}</option>
			</select>
		</div>
	</div>
	
	<div class="flex-grow flex min-h-0 relative">
		<div id="ai-action-spinner-overlay"
		     class="hidden absolute inset-0 bg-base-100/80 backdrop-blur-sm flex items-center justify-center z-50">
			<div class="text-center">
				<span class="loading loading-spinner loading-lg"></span>
				<p class="mt-2 text-sm">{{ $tr('editor.waitingAi', 'Waiting for AI response...') }}</p>
			</div>
		</div>
		<div id="js-source-column-container" class="w-1/2 overflow-y-auto border-r border-base-300">
			<!-- Source content will be rendered here by JS -->
		</div>
		<div id="js-target-column-container" class="w-1/2 overflow-y-auto">
			<!-- Target iframes will be rendered here by JS -->
		</div>
	</div>
</div>

<div id="bottom-status-bar"
     class="flex-shrink-0 h-8 bg-base-100 flex items-center px-4 gap-4 z-40 border-t border-base-300 dark:border-base-100/10 text-sm">
	<span id="js-tm-status" class="text-base-content/70"></span>
	<span id="js-codex-status" class="text-base-content/70 ml-4"></span>
	<div class="flex-grow"></div>
	<div class="flex items-center text-base-content/70">
		<span id="js-total-word-count" class="mr-4"></span>
		<span id="js-word-count">{{ $tr('editor.noTextSelected', 'No text selected') }}</span>
	</div>
</div>

<div id="modal-placeholders">
	@php echo \App\Support\PageData::translateHtml(file_get_contents(resource_path('legacy/templates/modals/prompt-editor-modal.blade.php')), $translations ?? [], $englishTranslations ?? []); @endphp
	@php echo \App\Support\PageData::translateHtml(file_get_contents(resource_path('legacy/templates/modals/alert-modal.blade.php')), $translations ?? [], $englishTranslations ?? []); @endphp
	@php echo \App\Support\PageData::translateHtml(file_get_contents(resource_path('legacy/templates/modals/typography-settings-modal.blade.php')), $translations ?? [], $englishTranslations ?? []); @endphp
	@php echo \App\Support\PageData::translateHtml(file_get_contents(resource_path('legacy/templates/modals/dictionary-modal.blade.php')), $translations ?? [], $englishTranslations ?? []); @endphp
	@php echo \App\Support\PageData::translateHtml(file_get_contents(resource_path('legacy/templates/modals/confirmation-modal.blade.php')), $translations ?? [], $englishTranslations ?? []); @endphp
	@php echo \App\Support\PageData::translateHtml(file_get_contents(resource_path('legacy/templates/modals/input-modal.blade.php')), $translations ?? [], $englishTranslations ?? []); @endphp
		
		<!-- NEW BLOCK START: Modal dialog to prompt TM changes highlight before processing updates -->
	<dialog id="tm-confirm-modal" class="modal">
		<div class="modal-box w-11/12 max-w-lg flex flex-col">
			<h3
				class="font-bold text-lg text-primary">{{ $tr('editor.translationMemory.updateTitle', 'Update Translation Memory') }}</h3>
			<p class="py-2 text-sm text-base-content/70">
				We detected changes in your translated text. Would you like to add these changes to your Translation Memory?
			</p>
			<div class="my-4 p-4 bg-base-200 rounded-md overflow-y-auto max-h-60 font-mono text-sm whitespace-pre-wrap"
			     id="tm-confirm-diff">
				<!-- Highlighted changes will go here -->
			</div>
			<div class="modal-action">
				<button id="tm-confirm-cancel-btn" class="btn">No, Skip</button>
				<button id="tm-confirm-save-btn" class="btn btn-primary">Yes, Update TM</button>
			</div>
		</div>
	</dialog>
	<!-- NEW BLOCK END -->
</div>

<dialog id="chat-dialog" class="modal">
	<div class="modal-box w-11/12 max-w-5xl h-[88vh] p-0 flex flex-col overflow-hidden">
		<div class="flex items-center justify-between px-4 py-3 border-b border-base-300">
			<h3 class="font-bold text-lg">{{ $tr('editor.chat.title', 'AI Chat') }}</h3>
			<form method="dialog">
				<button class="btn btn-sm btn-circle btn-ghost" title="{{ $tr('common.close', 'Close') }}">
					<i class="bi bi-x-lg"></i>
				</button>
			</form>
		</div>
		<iframe id="chat-dialog-frame" class="flex-grow w-full border-0" src="about:blank"></iframe>
	</div>
</dialog>

<template id="template-prompt-rephrase-editor">
	@php echo \App\Support\PageData::translateHtml(file_get_contents(resource_path('legacy/templates/prompt/rephrase-editor.blade.php')), $translations ?? [], $englishTranslations ?? []); @endphp
</template>

<template id="template-prompt-translate-editor">
	@php echo \App\Support\PageData::translateHtml(file_get_contents(resource_path('legacy/templates/prompt/translate-editor.blade.php')), $translations ?? [], $englishTranslations ?? []); @endphp
</template>

<template id="template-editor-source-chapter">
	@verbatim
		<div id="source-chapter-scroll-target-{{id}}" class="manuscript-chapter-item px-8 py-6" data-chapter-id="{{id}}">
			<div class="js-source-column prose prose-sm dark:prose-invert max-w-none bg-base-200 p-4 rounded-lg">
				<div class="flex justify-between items-center border-b pb-1 mb-2">
					<div
						class="js-sync-scroll-btn flex items-center gap-2 cursor-pointer hover:bg-base-content/10 p-1 -m-1 rounded-md transition-colors flex-grow"
						data-chapter-id="{{id}}" data-direction="source-to-target" title="{{sync_source_title}}">
						<h3 class="!mt-0 text-sm font-semibold uppercase tracking-wider text-base-content/70 flex-grow">{{title}}
							(<span class="js-source-word-count">{{source_word_count}} <span>{{words_label}}</span></span>)</h3>
						<span class="text-base-content/70 text-lg"><i class="bi bi-arrow-right-circle"></i></span>
					</div>
					<div class="js-source-actions flex items-center gap-1 pl-2">
						<button class="js-edit-source-btn btn btn-ghost btn-xs">{{edit_label}}</button>
						<button class="js-save-source-btn btn btn-success btn-xs hidden">{{save_label}}</button>
						<button class="js-cancel-source-btn btn btn-ghost btn-xs hidden">{{cancel_label}}</button>
						<div class="dropdown dropdown-end">
							<button tabindex="0" role="button" class="btn btn-ghost btn-xs btn-circle"><i
									class="bi bi-three-dots-vertical"></i></button>
							<ul tabindex="0" class="dropdown-content z-[1] menu p-2 shadow bg-base-200 rounded-box w-52">
								<li>
									<button class="js-chapter-action" data-action="rename"
									        data-chapter-id="{{id}}">{{rename_label}}</button>
								</li>
								<li>
									<button class="js-chapter-action" data-action="insert-above"
									        data-chapter-id="{{id}}">{{insert_above_label}}</button>
								</li>
								<li>
									<button class="js-chapter-action" data-action="insert-below"
									        data-chapter-id="{{id}}">{{insert_below_label}}</button>
								</li>
								<div class="divider my-1"></div>
								<li>
									<button class="js-chapter-action text-error" data-action="delete"
									        data-chapter-id="{{id}}">{{delete_label}}</button>
								</li>
							</ul>
						</div>
					</div>
				</div>
				<div class="source-content-readonly" spellcheck="false">{{source_content}}</div>
			</div>
		</div>
	@endverbatim
</template>

<template id="template-editor-target-chapter">
	@verbatim
		<div id="target-chapter-scroll-target-{{id}}" class="px-8 py-6" data-chapter-id="{{id}}">
			<div class="flex justify-between items-center border-b pb-1 mb-2 pt-4">
				<div
					class="js-sync-scroll-btn flex items-center gap-2 cursor-pointer hover:bg-base-content/10 p-1 -m-1 rounded-md transition-colors flex-grow"
					data-chapter-id="{{id}}" data-direction="target-to-source" title="{{sync_target_title}}">
					<h3 class="!mt-0 text-sm font-semibold uppercase tracking-wider text-base-content/70 flex-grow">{{title}}
						(<span
							class="js-target-word-count">{{target_word_count}} <span>{{words_label}}</span></span>)</h3>
					<span class="text-base-content/70 text-lg"><i class="bi bi-arrow-left-circle"></i></span>
				</div>
			</div>
			<iframe class="js-target-content-editable w-full border-0 min-h-[100px]" src="/editor-iframe"
			        data-chapter-id="{{id}}"></iframe>
		</div>
	@endverbatim
</template>

<!-- NEW: Pass route parameters to frontend JS -->
<script>
	window.routeParams = {
		bookId: @json($bookId ?? null),
		chapterId: @json($chapterId ?? null)
	};
	window.appSelectedLanguage = @json($selectedLang ?? 'en');
	window.initialLanguageFiles = {
		en: @json($englishTranslations ?? []),
		[@json($selectedLang ?? 'en')]: @json($translations ?? [])
	};
	window.initialModels = @json($modelsData ?? ['success' => true, 'models' => []]);
</script>
<script src="/js/api.js"></script>
<script src="/src/js/theme.js"></script>
<script type="module" src="/dist/chapter-editor-bundle.js"></script>
</body>
</html>

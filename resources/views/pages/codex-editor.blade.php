<!DOCTYPE html>
<html lang="{{ $selectedLang ?? 'en' }}">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Codex Editor</title>
	<link rel="stylesheet" href="{{ asset('vendor/bootstrap-icons/bootstrap-icons.css') }}">
	<link rel="stylesheet" href="/dist/styles.css">
</head>
<body class="bg-base-100 min-h-screen p-8 text-base-content">
<div class="container mx-auto max-w-7xl">
	<div class="flex justify-between items-center mb-6 pb-4 border-b border-base-300">
		<!-- MODIFIED: Server-side translation loader via the $tr helper -->
		<h1 class="text-4xl font-bold">{{ $tr('editor.codex.openEditor', 'Codex Editor') }}</h1>
		<a href="/dashboard" class="btn btn-sm btn-outline">{{ $tr('editor.codex.editor.backToDashboard', 'Back to Dashboard') }}</a>
	</div>
	
	<div id="codex-container">
		<!-- Loader message -->
		<p id="codex-loading-text" class="text-base-content/70">Loading...</p>
		
		<!-- Codex Editor panel -->
		<div id="codex-editor-view" class="hidden">
			<div class="mb-4 flex items-center justify-between gap-3">
				<!-- MODIFIED: Server-side status string fallback loaded dynamically -->
				<span id="codex-generation-status" class="text-sm text-base-content/70" data-i18n="editor.codex.statusLabel.incomplete_none">{{ $tr('editor.codex.statusLabel.incomplete_none', 'Incomplete') }}</span>
			</div>
			
			<h2 id="codex-title" class="text-2xl font-semibold mb-4"></h2>
			
			<div class="grid grid-cols-1 md:grid-cols-[1fr_auto_auto_auto] gap-3 mb-4 items-end">
				<label class="form-control">
					<!-- MODIFIED: Model label translated using $tr -->
					<span id="codex-label-model" class="label-text" data-i18n="editor.codex.editor.model">{{ $tr('editor.codex.editor.model', 'Model') }}</span>
					<select id="codex-model-select" class="select select-bordered">
						<!-- Options populated dynamically -->
					</select>
				</label>
				
				<label class="form-control w-52">
					<!-- MODIFIED: Language select label translated using $tr -->
					<span id="codex-label-language" class="label-text" data-i18n="editor.codex.editor.language">{{ $tr('editor.codex.editor.language', 'Codex Language') }}</span>
					<select id="codex-language-select" class="select select-bordered">
						<!-- Options populated dynamically -->
					</select>
				</label>
				
				<label class="form-control w-40">
					<!-- MODIFIED: Temperature label translated using $tr -->
					<span id="codex-label-temperature" class="label-text" data-i18n="editor.codex.editor.temperature">{{ $tr('editor.codex.editor.temperature', 'Temperature') }}</span>
					<input id="codex-temperature-input" type="number" min="0" max="2" step="0.1" class="input input-bordered">
				</label>
				
				<!-- MODIFIED: Rebuild button text translated using $tr -->
				<button id="codex-rebuild-btn" class="btn btn-warning" data-i18n="editor.codex.editor.rebuildBtn">{{ $tr('editor.codex.editor.rebuildBtn', 'Rebuild Codex') }}</button>
			</div>
			
			<div id="codex-progress-container" class="hidden mb-4 space-y-1">
				<div class="flex justify-between text-xs font-semibold text-base-content/70">
					<!-- MODIFIED: Generating progress label translated using $tr -->
					<span id="codex-label-generating" data-i18n="editor.codex.editor.generating">{{ $tr('editor.codex.editor.generating', 'Generating Codex...') }}</span>
					<span id="codex-progress-percent">0%</span>
				</div>
				<progress id="codex-progress-bar" class="progress progress-primary w-full" value="0" max="100"></progress>
			</div>
			
			<div class="form-control">
				<!-- MODIFIED: Codex plain-text content label translated using $tr -->
				<label class="label"><span id="codex-label-content" class="label-text" data-i18n="editor.codex.editor.plainTextLabel">{{ $tr('editor.codex.editor.plainTextLabel', 'Codex Plain Text Content') }}</span></label>
				<textarea id="codex-textarea" class="textarea textarea-bordered w-full h-96 font-mono"></textarea>
			</div>
			
			<div class="form-control mt-6">
				<!-- MODIFIED: Save button text translated using $tr -->
				<button id="codex-save-btn" class="btn btn-success" data-i18n="editor.codex.editor.saveBtn">{{ $tr('editor.codex.editor.saveBtn', 'Save Codex') }}</button>
			</div>
		</div>
	</div>
</div>

<!-- Dynamic HTML alert-modal & confirmation-modal blocks -->
@php
	echo \App\Support\PageData::translateHtml(
			file_get_contents(resource_path('legacy/templates/modals/alert-modal.blade.php')),
			$translations ?? [],
			$englishTranslations ?? []
	);
	echo \App\Support\PageData::translateHtml(
			file_get_contents(resource_path('legacy/templates/modals/confirmation-modal.blade.php')),
			$translations ?? [],
			$englishTranslations ?? []
	);
@endphp
	
	<!-- Pass route parameters to frontend JS -->
<script>
	window.routeParams = {
		bookId: @json($bookId ?? null)
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
<script src="/src/js/modals.js"></script>
<script type="module" src="/src/js/codex-editor.js"></script>
</body>
</html>

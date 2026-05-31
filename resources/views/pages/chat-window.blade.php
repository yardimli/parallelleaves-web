<!DOCTYPE html>
<html lang="{{ $selectedLang ?? 'en' }}" class="h-full">
<head>
	<meta charset="UTF-8"/>
	<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no"/>
	<title>{{ $tr('editor.chat.title', 'Parallel Leaves - AI Chat') }}</title>
	<link rel="stylesheet" href="{{ asset('vendor/bootstrap-icons/bootstrap-icons.css') }}">
	<link rel="stylesheet" href="/dist/styles.css">
</head>
<body class="h-full bg-base-100 text-base-content overflow-hidden flex flex-col">

<!-- Top Bar -->
<div class="flex-shrink-0 h-14 bg-base-200 flex items-center px-4 gap-4 z-10 border-b border-base-300">
	<h1 class="text-lg font-bold">{{ $tr('editor.chat.title', 'AI Chat') }}</h1>
	<div class="flex-grow"></div>
	<select id="js-llm-model-select" class="select select-bordered select-sm w-full max-w-xs">
		@forelse(($modelsData['models'] ?? []) as $group)
			<optgroup label="{{ $group['group'] }}">
				@foreach($group['models'] as $model)
					<option value="{{ $model['id'] }}">{{ $model['name'] }}</option>
				@endforeach
			</optgroup>
		@empty
			<option disabled selected>{{ $tr('editor.chat.loadingModels', 'Loading models...') }}</option>
		@endforelse
	</select>
	<!-- Temperature Slider -->
	<div class="flex items-center gap-2">
		<label class="text-sm font-medium">{{ $tr('editor.temperature', 'Temperature') }}</label>
		<input type="range" min="0" max="2" value="0.7" step="0.1" id="js-ai-temperature-slider"
		       class="range range-xs w-24"/>
		<span id="js-ai-temperature-value" class="text-sm font-mono w-8 text-center">0.7</span>
	</div>
</div>

<!-- Chat Management and Chapter Selector - NEW BLOCK -->
<div class="flex-shrink-0 p-4 border-b border-base-300 bg-base-200 flex flex-wrap items-center gap-4">
	<!-- Chat Selector -->
	<div class="dropdown dropdown-hover">
		<div tabindex="0" role="button" class="btn btn-sm" id="js-current-chat-name">{{ $tr('editor.chat.newChat', 'New Chat') }}</div>
		<ul tabindex="0" class="dropdown-content z-[1] menu p-2 shadow bg-base-100 rounded-box w-52" id="js-chat-list">
			<!-- Chat history items will be dynamically inserted here -->
		</ul>
	</div>
	<button id="js-new-chat-btn" class="btn btn-sm btn-outline"><i class="bi bi-plus-circle"></i> <span
		>{{ $tr('editor.chat.newChat', 'New Chat') }}</span></button>
	<button id="js-delete-chat-btn" class="btn btn-sm btn-outline btn-error"><i class="bi bi-trash"></i> <span
		>{{ $tr('editor.chat.deleteChat', 'Delete Chat') }}</span></button>
	
	<!-- Chapter Selector -->
	<select id="js-chapter-select" class="select select-bordered select-sm flex-grow min-w-[150px]">
		<option value="" disabled selected>{{ $tr('editor.chat.selectChapter', 'Select Chapter (Optional)') }}</option>
		<option value="none">{{ $tr('editor.chat.noChapter', 'No Chapter') }}</option>
		@foreach(($chapters ?? []) as $chapter)
			<option value="{{ $chapter['id'] }}">{{ $chapter['title'] }}</option>
		@endforeach
	</select>
</div>

<!-- Chat History -->
<div id="js-chat-history" class="flex-grow p-4 overflow-y-auto space-y-4">
	<!-- Messages will be appended here by JS -->
</div>

<!-- Input Area -->
<div class="flex-shrink-0 p-4 border-t border-base-300 bg-base-200">
	<form id="js-chat-form" class="flex items-start gap-2">
<textarea id="js-chat-input" class="textarea textarea-bordered w-full resize-none" rows="2"
          placeholder="{{ $tr('editor.chat.sendMessagePlaceholder', '') }}"></textarea>
		<button type="submit" id="js-send-btn" class="btn btn-primary btn-square">
			<i class="bi bi-send-fill text-lg"></i>
		</button>
	</form>
</div>

<!-- NEW: Pass route parameters to frontend JS -->
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
	window.initialChapters = @json($chapters ?? []);
</script>
<script src="/js/api.js"></script>
<script src="/src/js/theme.js"></script>
<script type="module" src="/src/js/chat-window.js"></script>
</body>
</html>

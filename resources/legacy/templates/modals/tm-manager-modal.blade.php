<dialog id="tm-manager-modal" class="modal">
	<div class="modal-box w-11/12 max-w-4xl h-[90vh] flex flex-col p-0">
		<!-- Header Section -->
		<div class="flex-shrink-0 p-6 pb-4 border-b border-base-300">
			<form method="dialog">
				<button class="btn btn-sm btn-circle btn-ghost absolute right-2 top-2">✕</button>
			</form>
			<h3 class="font-bold text-lg mb-2">{{__i18n:editor.translationMemory.openEditor|Translation Memory__}}</h3>
			<p class="text-sm text-base-content/70" data-i18n="editor.translationMemory.description">{{__i18n:editor.translationMemory.description|View, search, edit, or delete individual sentences in your Translation Memory database.__}}</p>
		</div>
		
		<!-- Controls Bar -->
		<div class="flex-shrink-0 px-6 py-4 border-b border-base-300 bg-base-200/50 flex flex-wrap items-center justify-between gap-4">
			<!-- Search bar -->
			<div class="relative w-full md:w-72">
                <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-base-content/50 pointer-events-none">
                    <i class="bi bi-search"></i>
                </span>
				<input type="text" id="tm-search-input" class="input input-sm input-bordered w-full pl-10" data-i18n-placeholder="editor.translationMemory.searchPlaceholder" placeholder="{{__i18n:editor.translationMemory.searchPlaceholder|Search memories...__}}">
			</div>
			
			<div class="flex items-center gap-4">
				<button type="button" id="tm-open-purge-btn" class="btn btn-xs btn-outline btn-error">
					<i class="bi bi-funnel"></i>
					<span>{{__i18n:editor.translationMemory.purge.open|Purge__}}</span>
				</button>

				<!-- Items Per Page Dropdown -->
				<div class="flex items-center gap-2">
					<span class="text-xs font-semibold" data-i18n="editor.translationMemory.show">{{__i18n:editor.translationMemory.show|Show:__}}</span>
					<select id="tm-per-page-select" class="select select-bordered select-xs">
						<option value="10" selected>10</option>
						<option value="25">25</option>
						<option value="50">50</option>
					</select>
				</div>
				
				<!-- Pagination navigation and information -->
				<div class="flex items-center gap-2">
					<button type="button" id="tm-prev-page-btn" class="btn btn-xs btn-outline btn-square" disabled>
						<i class="bi bi-chevron-left"></i>
					</button>
					<span id="tm-pagination-info" class="text-xs font-semibold">Page 1 of 1</span>
					<button type="button" id="tm-next-page-btn" class="btn btn-xs btn-outline btn-square" disabled>
						<i class="bi bi-chevron-right"></i>
					</button>
				</div>
			</div>
		</div>
		
		<!-- Scrollable Content Area -->
		<div class="flex-grow overflow-y-auto min-h-0">
			<div class="p-6 space-y-4" id="tm-cards-container">
				<!-- Dynamic cards will be populated here under each other by JS -->
			</div>
			<p id="tm-no-entries" class="text-center text-base-content/60 py-12 hidden" data-i18n="editor.translationMemory.noEntries">{{__i18n:editor.translationMemory.noEntries|No translation memory matches found.__}}</p>
		</div>
		
		<!-- Footer Section -->
		<div class="flex-shrink-0 px-6 py-4 border-t border-base-300">
			<div class="modal-action p-0 mt-0">
				<form method="dialog" class="flex gap-3 w-full justify-end">
					<button class="btn btn-ghost">{{__i18n:common.close|Close__}}</button>
				</form>
			</div>
		</div>
	</div>
</dialog>

<dialog id="tm-purge-dialog" class="modal">
	<div class="modal-box w-11/12 max-w-2xl">
		<form method="dialog">
			<button id="tm-purge-close-btn" class="btn btn-sm btn-circle btn-ghost absolute right-2 top-2">âœ•</button>
		</form>

		<h3 class="font-bold text-lg mb-4">{{__i18n:editor.translationMemory.purge.title|Purge Translation Memory__}}</h3>

		<div class="space-y-4">
			<label class="form-control">
				<span class="label-text font-semibold">{{__i18n:editor.translationMemory.purge.rule|Purge rule__}}</span>
				<textarea id="tm-purge-rule-input" class="textarea textarea-bordered min-h-28">only tenses are different everything else is the same</textarea>
			</label>

			<label class="form-control">
				<span class="label-text font-semibold">{{__i18n:editor.translationMemory.purge.model|Model__}}</span>
				<select id="tm-purge-model-select" class="select select-bordered">
					<option disabled selected>{{__i18n:editor.chat.loadingModels|Loading models...__}}</option>
				</select>
			</label>

			<div id="tm-purge-progress-container" class="hidden space-y-2">
				<div class="flex justify-between text-sm font-semibold">
					<span id="tm-purge-progress-label">{{__i18n:editor.translationMemory.purge.progress|Checking entries...__}}</span>
					<span id="tm-purge-progress-count">0 / 0</span>
				</div>
				<progress id="tm-purge-progress-bar" class="progress progress-error w-full" value="0" max="100"></progress>
				<p id="tm-purge-result-text" class="text-sm text-base-content/70"></p>
			</div>
		</div>

		<div class="modal-action">
			<form method="dialog">
				<button id="tm-purge-cancel-btn" class="btn btn-ghost">{{__i18n:common.cancel|Cancel__}}</button>
			</form>
			<button type="button" id="tm-purge-start-btn" class="btn btn-error">
				<i class="bi bi-trash"></i>
				<span>{{__i18n:editor.translationMemory.purge.start|Start purge__}}</span>
			</button>
		</div>
	</div>
</dialog>

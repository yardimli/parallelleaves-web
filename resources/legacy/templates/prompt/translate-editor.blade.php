<div class="py-1 px-6">
	<form id="translate-editor-form" class="space-y-3">
		<!-- Instructions -->
		<div>
			<label for="translate-instructions" class="label">
				<span class="label-text text-lg font-semibold">{{__i18n:prompt.translate.instructions|Instructions__}}</span>
				<button type="button" class="js-copy-style-analysis-btn btn btn-xs btn-outline">{{__i18n:prompt.translate.copyStyleAnalysis|Copy Style Analysis__}}</button>
			</label>
			<textarea id="translate-instructions" name="instructions" class="textarea textarea-bordered w-full h-24 font-mono text-sm" placeholder="{{__i18n:prompt.translate.instructionsPlaceholder__}}"></textarea>
			<label class="label cursor-pointer justify-start gap-2">
				<input type="checkbox" name="include_instructions" class="checkbox checkbox-sm" checked>
				<span class="label-text">{{__i18n:prompt.translate.includeInstructions|Include instructions in prompt__}}</span>
			</label>
			<!-- Help text moved below -->
		</div>
		
		<div class="flex items-end gap-4">
			<!-- Tense Selection -->
			<div class="flex-1">
				<label class="label"><span class="label-text text-lg font-semibold">{{__i18n:prompt.common.tense|Tense__}}</span></label>
				<div class="join w-full js-tense-group">
					<button type="button" class="js-tense-btn btn join-item flex-1 btn-active" data-tense="past">{{__i18n:prompt.common.past|Past__}}</button>
					<button type="button" class="js-tense-btn btn join-item flex-1" data-tense="present">{{__i18n:prompt.common.present|Present__}}</button>
					<button type="button" class="js-tense-btn btn join-item flex-1" data-tense="none">{{__i18n:prompt.common.none|None__}}</button>
				</div>
				<input type="hidden" name="tense" value="past">
			</div>
			
			<!-- Previous Translation Pairs -->
			<div class="flex-1">
				<label for="translate-context-pairs" class="label pb-1">
					<span class="label-text font-semibold">{{__i18n:prompt.translate.prevPairs|Previous Translation Pairs__}}</span>
				</label>
				<input type="number" id="translate-context-pairs" name="context_pairs" class="input input-bordered w-full" value="4" min="0" max="10">
				<!-- Help text moved below -->
			</div>
		</div>
		
		<!-- MODIFIED: Removed the Translation Memory select dropdown -->
	</form>
	
	<div class="divider my-2"></div>
	
	<div class="js-live-preview-section hidden">
		<div class="flex items-center justify-between gap-3">
			<h2 class="text-xl font-bold">{{__i18n:prompt.preview.title|Live Prompt Preview__}}</h2>
			<button type="button" class="js-expand-placeholders-btn btn btn-xs btn-outline">{{__i18n:prompt.preview.expandPlaceholders|Expand Placeholders__}}</button>
		</div>
		<p class="text-sm text-base-content/70 mb-4">{{__i18n:prompt.preview.description|This is the final prompt that will be sent to the AI, based on your settings above.__}}</p>
		
		<!-- System Prompt Preview -->
		<div>
			<h3 class="text-lg font-semibold mt-4 font-mono text-success">{{__i18n:prompt.preview.system|System Prompt__}}</h3>
			<pre class="bg-base-200 p-4 rounded-md text-xs whitespace-pre-wrap font-mono"><code class="js-preview-system"></code></pre>
		</div>
		
		<div class="js-preview-context-pairs">
			<!-- This will be populated by JS to show previous user/assistant messages -->
		</div>
		
		<!-- User Prompt Preview -->
		<div>
			<h3 class="text-lg font-semibold mt-4 font-mono text-info">{{__i18n:prompt.preview.user|User Prompt__}}</h3>
			<pre class="bg-base-200 p-4 rounded-md text-xs whitespace-pre-wrap font-mono"><code class="js-preview-user"></code></pre>
		</div>
		
		<!-- AI Prefix Preview -->
		<div>
			<h3 class="text-lg font-semibold mt-4 font-mono text-warning">{{__i18n:prompt.preview.aiPrefix|AI Response Prefix__}}</h3>
			<pre class="bg-base-200 p-4 rounded-md text-xs whitespace-pre-wrap font-mono"><code class="js-preview-ai">{{__i18n:prompt.preview.empty|(Empty)__}}</code></pre>
		</div>
	</div>
</div>

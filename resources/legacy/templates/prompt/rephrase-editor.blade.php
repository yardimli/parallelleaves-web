<div class="py-1 px-6">
	<form id="rephrase-editor-form" class="space-y-3">
		<!-- Instructions -->
		<div>
			<label for="rephrase-instructions" class="label"><span class="label-text text-lg font-semibold">{{__i18n:prompt.rephrase.instructions|Instructions__}}</span></label>
			<textarea id="rephrase-instructions" name="instructions" class="textarea textarea-bordered w-full h-24 font-mono text-sm" placeholder="{{__i18n:prompt.rephrase.instructionsPlaceholder__}}"></textarea>
		</div>
		
		<!-- Tense Selection -->
		<div>
			<label class="label"><span class="label-text text-lg font-semibold">{{__i18n:prompt.common.tense|Tense__}}</span></label>
			<div class="btn-group w-full js-tense-group">
				<button type="button" class="js-tense-btn btn flex-1 btn-active" data-tense="past">{{__i18n:prompt.common.past|Past__}}</button>
				<button type="button" class="js-tense-btn btn flex-1" data-tense="present">{{__i18n:prompt.common.present|Present__}}</button>
				<button type="button" class="js-tense-btn btn flex-1" data-tense="none">{{__i18n:prompt.common.none|None__}}</button>
			</div>
			<input type="hidden" name="tense" value="past">
		</div>
	</form>
	
	<div class="divider my-2"></div>
	
	<div class="js-live-preview-section hidden">
		<h2 class="text-xl font-bold">{{__i18n:prompt.preview.title|Live Prompt Preview__}}</h2>
		<p class="text-sm text-base-content/70 mb-4">{{__i18n:prompt.preview.description|This is the final prompt that will be sent to the AI, based on your settings above.__}}</p>
		
		<!-- System Prompt Preview -->
		<div>
			<h3 class="text-lg font-semibold mt-4 font-mono text-success">{{__i18n:prompt.preview.system|System Prompt__}}</h3>
			<pre class="bg-base-200 p-4 rounded-md text-xs whitespace-pre-wrap font-mono"><code class="js-preview-system"></code></pre>
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

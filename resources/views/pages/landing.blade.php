<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Parallel Leaves - Interactive Translation Workspace</title>
	<link rel="stylesheet" href="/node_modules/bootstrap-icons/font/bootstrap-icons.css">
	<link rel="stylesheet" href="/dist/styles.css">
</head>
<body class="bg-base-100 text-base-content min-h-screen flex flex-col">
<!-- Navbar header -->
<header class="navbar bg-base-200 border-b border-base-300 px-4 md:px-8">
	<div class="flex-1 gap-2">
		<!-- App Logo using assets directory image -->
		<img src="/assets/android-chrome-192x192.png" alt="Parallel Leaves Brand Logo" class="w-8 h-8 rounded-lg">
		<span class="text-xl font-bold tracking-tight">Parallel Leaves</span>
	</div>
	<div class="flex-none gap-2">
		<!-- Theme Switcher Button -->
		<button id="theme-toggle" class="btn btn-ghost btn-circle" aria-label="Toggle Theme">
			<i class="bi bi-sun-fill text-xl"></i>
		</button>
		@auth
			<!-- Authenticated navigation elements -->
			<a href="/dashboard" class="btn btn-primary btn-sm">Go to Dashboard</a>
			<button id="logout-btn" class="btn btn-outline btn-sm">Sign Out</button>
		@else
			<!-- Guest navigation elements -->
			<a href="/login" class="btn btn-ghost btn-sm">Sign In</a>
			<a href="/register" class="btn btn-primary btn-sm">Register</a>
		@endauth
	</div>
</header>

<!-- Main landing content workspace -->
<main class="flex-grow">
	<!-- Hero Presentation Block -->
	<section class="hero min-h-[50vh] bg-base-200 py-12 px-4 md:px-8">
		<div class="hero-content flex-col lg:flex-row-reverse gap-8 max-w-7xl mx-auto">
			<!-- Splash Artwork visualizer -->
			<div class="max-w-md lg:max-w-lg rounded-2xl overflow-hidden shadow-2xl border border-base-300">
				<img src="/assets/splash-v1.png" onerror="this.src='/assets/splash-v1.jpg'" alt="Parallel Leaves Workspace Splash Artwork" class="w-full h-auto">
			</div>
			<!-- Hero context -->
			<div class="max-w-xl">
				<h1 class="text-5xl font-extrabold tracking-tight leading-none mb-6">
					Refine Prose Translation with <span class="text-primary">AI Context</span>
				</h1>
				<p class="text-lg text-base-content/70 mb-8">
					Parallel Leaves is a side-by-side bilingual workbook environment. Import your manuscripts, establish custom stylistic translation memory maps, and align text structures with highly tailorable, contextualized large language models.
				</p>
				<div class="flex gap-4">
					@auth
						<a href="/dashboard" class="btn btn-primary btn-md">Go to Dashboard &rarr;</a>
					@else
						<a href="/register" class="btn btn-primary btn-md">Get Started</a>
						<a href="/login" class="btn btn-outline btn-md">Sign In</a>
					@endauth
				</div>
			</div>
		</div>
	</section>
	
	<!-- Complete Scanned Features Grid -->
	<section class="py-16 px-4 md:px-8 max-w-7xl mx-auto">
		<h2 class="text-3xl font-bold text-center mb-4">Comprehensive Application Features</h2>
		<p class="text-center text-base-content/60 max-w-2xl mx-auto mb-12">
			Discover tools specifically engineered for prose writers and literary translation workflows to ensure absolute stylistic coherence.
		</p>
		
		<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
			<!-- Feature item 1: Manuscript parser -->
			<div class="card bg-base-200 border border-base-300">
				<div class="card-body">
					<div class="text-primary mb-2"><i class="bi bi-file-earmark-arrow-up text-3xl"></i></div>
					<h3 class="card-title text-lg font-bold">Manuscript Import</h3>
					<p class="text-sm text-base-content/70">
						Upload formatted .docx Word documents or flat text .txt drafts. The system parses nested elements and flattens them cleanly into independent paragraphs.
					</p>
				</div>
			</div>
			
			<!-- Feature item 2: Segmenter -->
			<div class="card bg-base-200 border border-base-300">
				<div class="card-body">
					<div class="text-primary mb-2"><i class="bi bi-scissors text-3xl"></i></div>
					<h3 class="card-title text-lg font-bold">Intelligent Chapter Segmenter</h3>
					<p class="text-sm text-base-content/70">
						Auto-detect manuscript chapters using advanced pattern matches (such as numerical index lines, Roman numerals, keywords, or All-Caps lines) to split huge documents cleanly.
					</p>
				</div>
			</div>
			
			<!-- Feature item 3: Side editor -->
			<div class="card bg-base-200 border border-base-300">
				<div class="card-body">
					<div class="text-primary mb-2"><i class="bi bi-columns-gap text-3xl"></i></div>
					<h3 class="card-title text-lg font-bold">Synchronized Dual-Pane Editor</h3>
					<p class="text-sm text-base-content/70">
						Work with a side-by-side editing split using interactive ProseMirror iframes. Includes scroll lock sync, formatting controls, and customizable themes.
					</p>
				</div>
			</div>
			
			<!-- Feature item 4: Translators -->
			<div class="card bg-base-200 border border-base-300">
				<div class="card-body">
					<div class="text-primary mb-2"><i class="bi bi-stars text-3xl"></i></div>
					<h3 class="card-title text-lg font-bold">Contextual AI Workspace</h3>
					<p class="text-sm text-base-content/70">
						Initiate LLM operations via OpenRouter. Customize tone instructions, translation style references, grammatical tenses, and context configurations dynamically.
					</p>
				</div>
			</div>
			
			<!-- Feature item 5: TM -->
			<div class="card bg-base-200 border border-base-300">
				<div class="card-body">
					<div class="text-primary mb-2"><i class="bi bi-book-fill text-3xl"></i></div>
					<h3 class="card-title text-lg font-bold">Translation Memory Maps</h3>
					<p class="text-sm text-base-content/70">
						Background jobs process parsed segments, compiling style guides from translation pairs to feed stylistic examples into future AI generations.
					</p>
				</div>
			</div>
			
			<!-- Feature item 6: Codex -->
			<div class="card bg-base-200 border border-base-300">
				<div class="card-body">
					<div class="text-primary mb-2"><i class="bi bi-journal-bookmark-fill text-3xl"></i></div>
					<h3 class="card-title text-lg font-bold">Automatic World Codex</h3>
					<p class="text-sm text-base-content/70">
						Ensure world-building coherence. Background LLM batch processes scan text chapters to compile, update, and maintain descriptions of characters, settings, and lore.
					</p>
				</div>
			</div>
			
			<!-- Feature item 7: Term consistency -->
			<div class="card bg-base-200 border border-base-300">
				<div class="card-body">
					<div class="text-primary mb-2"><i class="bi bi-spellcheck text-3xl"></i></div>
					<h3 class="card-title text-lg font-bold">Glossary & Dictionary Control</h3>
					<p class="text-sm text-base-content/70">
						Enforce strict translations for proprietary concepts. Map exact words in your custom project glossary to keep key nomenclature consistent.
					</p>
				</div>
			</div>
			
			<!-- Feature item 8: AI covers -->
			<div class="card bg-base-200 border border-base-300">
				<div class="card-body">
					<div class="text-primary mb-2"><i class="bi bi-palette text-3xl"></i></div>
					<h3 class="card-title text-lg font-bold">Art Cover Generator</h3>
					<p class="text-sm text-base-content/70">
						Create aesthetic prompts from book titles and generate portrait book covers using advanced Fal.ai API model pipelines.
					</p>
				</div>
			</div>
			
			<!-- Feature item 9: AI chat -->
			<div class="card bg-base-200 border border-base-300">
				<div class="card-body">
					<div class="text-primary mb-2"><i class="bi bi-chat-left-dots-fill text-3xl"></i></div>
					<h3 class="card-title text-lg font-bold">Interactive AI Companion</h3>
					<p class="text-sm text-base-content/70">
						Discuss plot, continuity, styling preferences, or consult draft translations using an inline AI chat companion that knows your active chapter details.
					</p>
				</div>
			</div>
		</div>
	</section>
</main>

<!-- Footer branding -->
<footer class="footer footer-center p-8 bg-base-200 text-base-content/60 border-t border-base-300">
	<div>
		<img src="/assets/android-chrome-192x192.png" alt="Parallel Leaves Footer Logo" class="w-10 h-10 rounded-lg mx-auto mb-2 opacity-80">
		<p class="font-bold">Parallel Leaves Workspace</p>
		<p>Copyright &copy; 2026. All rights reserved.</p>
	</div>
</footer>

<script src="/js/api.js"></script>
<script src="/src/js/theme.js"></script>
<script>
	// NEW: Handling the logout trigger using StandardJS with semicolons
	document.addEventListener('DOMContentLoaded', function () {
		const logoutBtn = document.getElementById('logout-btn');
		if (logoutBtn) {
			logoutBtn.addEventListener('click', function () {
				window.api.logout().then(function () {
					window.location.reload();
				}).catch(function (error) {
					console.error('Logout request failed:', error);
				});
			});
		}
	});
</script>
</body>
</html>

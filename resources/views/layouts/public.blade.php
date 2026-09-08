<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>@yield('title', 'Parallel Leaves — Translate faster. Keep your voice.')</title>
<meta name="description" content="@yield('description', 'A translation workspace where human judgment and AI work together. Translate, rephrase, build a Codex, reuse translation memory, and export your work. Free today.')">
@hasSection('noindex')<meta name="robots" content="noindex, nofollow">@else
<link rel="canonical" href="{{ rtrim(config('public.url'), '/') . request()->getPathInfo() }}">@endif
<meta property="og:title" content="@yield('title', 'Parallel Leaves — Translate faster. Keep your voice.')">
<meta property="og:description" content="Your judgment. AI's speed. One thoughtful translation workspace. Free to use today.">
<meta property="og:type" content="website">
<meta property="og:image" content="{{ rtrim(config('public.url'), '/') }}/assets/features/editor.png">
<meta name="twitter:card" content="summary_large_image">
<link rel="icon" href="/assets/favicon.ico">
<link rel="stylesheet" href="/vendor/bootstrap-icons/bootstrap-icons.css">
<link rel="stylesheet" href="/src/css/public.css">
<script>try { const t=localStorage.getItem('theme'); document.documentElement.dataset.theme=['light','paper','dark'].includes(t)?t:'paper'; } catch(e) {}</script>
<script src="/src/js/public.js" defer></script>
</head>
<body>
<a href="#main" class="skip-link">Skip to content</a>
<header class="site-header"><div class="shell header-inner">
<a class="brand" href="/" aria-label="Parallel Leaves home"><img src="/assets/android-chrome-192x192.png" width="36" height="36" alt=""><span>parallel leaves<span class="brand-dot">.</span></span></a>
<button class="icon-button mobile-menu" data-menu-toggle aria-expanded="false" aria-controls="site-nav" aria-label="Open navigation"><i class="bi bi-list" aria-hidden="true"></i></button>
<nav id="site-nav" aria-label="Main navigation"><a href="/#features">Features</a><a href="/#how-it-works">How it works</a><a href="/subscription">Pricing</a><a href="/#faq">FAQ</a></nav>
<div class="header-actions"><button class="icon-button" data-theme-toggle aria-label="Change color theme"><i class="bi bi-circle-half" aria-hidden="true"></i></button>
@auth<a class="button small" href="/dashboard">My books ↗</a>@else<a class="sign-in-link" href="/login">Sign in</a><a class="button small" href="/register">Start free ↗</a>@endauth
</div></div></header>
<main id="main">@yield('content')</main>
<footer class="site-footer"><div class="shell footer-top"><div><a class="brand" href="/"><img src="/assets/android-chrome-192x192.png" width="32" height="32" alt="">parallel leaves.</a><p>More room for the human in translation.</p></div><nav aria-label="Footer navigation"><a href="/subscription">Pricing</a><a href="/terms">Terms & conditions</a><a href="/privacy">Privacy</a><a href="mailto:{{ config('public.support_email') }}">Contact us</a></nav></div><div class="shell footer-bottom"><span>© {{ date('Y') }} {{ config('public.operator') }} · {{ config('public.location') }}</span><span>Built for thoughtful translation.</span></div></footer>
<dialog id="screenshot-dialog" class="screenshot-dialog" aria-label="Workspace screenshot"><button class="icon-button" data-close-screenshot aria-label="Close screenshot">×</button><img alt=""><p></p></dialog>
@stack('scripts')
</body></html>


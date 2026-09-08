@extends('layouts.public')
@section('noindex', 'true')
@section('content')
<div class="shell auth-shell"><aside class="auth-story"><p class="eyebrow">YOUR WORDS. YOUR JUDGMENT.</p><h1>A little help.<br><em>A lot of possibility.</em></h1><p>A thoughtful workspace for translating books with AI — and making every sentence your own.</p><p class="auth-quote">“The first draft is a beginning.<br>The final word is yours.”</p><ul class="check-list"><li>Side-by-side translation and editing</li><li>Codex, dictionary, and translation memory</li><li>Export your writing and the context behind it</li></ul></aside><section class="auth-form-wrap"><div class="auth-card">@yield('form')</div></section></div>
@endsection

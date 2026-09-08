@props(['name', 'alt', 'caption', 'eager' => false])
@php
    [$imageWidth, $imageHeight] = [
        'new-book' => [328, 323], 'byok' => [288, 172],
        'export' => [778, 175], 'codex' => [1265, 712],
    ][$name] ?? [1280, 720];
@endphp
<figure class="feature-shot">
    <a href="/assets/features/{{ $name }}.png" data-screenshot data-caption="{{ $caption }}" aria-label="Enlarge: {{ $alt }}">
        <div class="shot-bar"><span class="shot-dots" aria-hidden="true">● ● ●</span><span>PARALLEL LEAVES / WORKSPACE</span><i class="bi bi-arrows-angle-expand" aria-hidden="true"></i></div>
        <img src="/assets/features/{{ $name }}.png" alt="{{ $alt }}" loading="{{ $eager ? 'eager' : 'lazy' }}" @if($eager) fetchpriority="high" @endif width="{{ $imageWidth }}" height="{{ $imageHeight }}">
    </a>
    <figcaption>{{ $caption }} <span aria-hidden="true">↗</span></figcaption>
</figure>

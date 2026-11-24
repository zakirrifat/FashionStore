{{--
    Section header: small eyebrow + title on the left, "Shop all" and optional
    prev/next arrows on the right. Arrows control the [data-rail] inside the
    nearest [data-rail-group] (see rails() in resources/js/motion.js).
    Params: $title, $eyebrow?, $link?, $linkText?, $arrows? (bool)
--}}
<div class="edge mb-6 md:mb-8 flex items-end justify-between gap-6">
    <div class="min-w-0">
        @if (filled($eyebrow ?? null))
            <p class="eyebrow text-mute mb-3" data-reveal>{{ $eyebrow }}</p>
        @endif
        @if (filled($title ?? null))
            <h2 class="section-title" data-split>{{ $title }}</h2>
        @endif
    </div>
    @if (filled($link ?? null) || ($arrows ?? false))
        <div class="flex items-center gap-6 shrink-0">
            @if (filled($link ?? null))
                <a href="{{ $link }}" class="label link-u" data-reveal>{{ ($linkText ?? null) ?: 'Shop all' }}</a>
            @endif
            @if ($arrows ?? false)
                <div class="hidden md:flex gap-2" data-rail-arrows data-reveal style="--d: 120ms">
                    <button type="button" class="rail-btn" data-rail-prev aria-label="Scroll back" disabled>←</button>
                    <button type="button" class="rail-btn" data-rail-next aria-label="Scroll forward">→</button>
                </div>
            @endif
        </div>
    @endif
</div>

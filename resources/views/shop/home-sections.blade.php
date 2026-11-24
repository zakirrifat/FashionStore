@extends('shop.layout')
@php use App\Support\Img; @endphp

{{-- Header floats over the first banner --}}
@if (in_array($sections[0]['type'] ?? null, ['hero', 'double_banner', 'slideshow'], true))
    @section('header-overlay', '1')
@endif

@section('content')
    @php
        // The selling-points strip sits under the first banner that actually shows.
        $stripAfter = collect($sections)->values()->search(fn ($s) => ($s['type'] ?? null) === 'hero'
            || (($s['type'] ?? null) === 'slideshow' && collect($s['data']['slides'] ?? [])->contains(fn ($slide) => filled($slide['image'] ?? null) || filled($slide['video'] ?? null))));
    @endphp
    @foreach ($sections as $section)
        @php $d = $section['data'] ?? []; $first = $loop->first; @endphp

        @switch($section['type'])

            {{-- ───── Banner slideshow (any number of slides) ───── --}}
            @case('slideshow')
                @php
                    $slides = array_values(array_filter($d['slides'] ?? [], fn ($s) => filled($s['image'] ?? null) || filled($s['video'] ?? null)));
                    $count = count($slides);
                    $seconds = max(3, min(15, (int) ($d['seconds'] ?? 6)));
                    $isSlide = ($d['transition'] ?? 'fade') === 'slide';
                    // Desktop uses the chosen height. Phones follow the picture's shape so the whole banner shows.
                    $height = ['full' => 'md:h-[100svh] md:min-h-[560px]', 'large' => 'md:h-[78svh] md:min-h-[480px]', 'medium' => 'md:h-[56svh] md:min-h-[380px]'][$d['height'] ?? 'full'];
                    $phoneRatio = collect($slides)->map(function ($s) {
                        $isVideo = ($s['media'] ?? 'image') === 'video' && filled($s['video'] ?? null);

                        return $isVideo ? 16 / 9 : (Img::ratio(($s['mobile_image'] ?? null) ?: ($s['image'] ?? null)) ?? 16 / 9);
                    })->min();
                    $phoneRatio = max(0.7, min(3, $phoneRatio)); // the tallest slide sets the frame, so none is cut
                    $phoneTop = $first ? 4 : 0;       // room for the header floating over the first section
                    $phoneBottom = $count > 1 ? 2 : 0; // room for the slide indicators
                    // Room under the picture for the tallest slide's text, so nothing covers the banner on phones.
                    $phoneText = collect($slides)->map(function ($s) {
                        $rows = (filled($s['eyebrow'] ?? null) ? 1.25 : 0) + (filled($s['heading'] ?? null) ? 2 : 0)
                            + (filled($s['button_text'] ?? null) && filled($s['link'] ?? null) ? 2.75 : 0);

                        return $rows ? $rows + 0.75 : 0;
                    })->max();
                @endphp
                @if ($count)
                <section class="relative {{ $height }} max-md:h-[calc(100vw/var(--phone-ratio)+var(--phone-extra))] overflow-hidden bg-night select-none {{ $first ? '' : 'section-mt' }}"
                         style="--phone-ratio: {{ number_format($phoneRatio, 4, '.', '') }}; --phone-extra: {{ $phoneTop + $phoneBottom + $phoneText }}rem; --phone-text: {{ $phoneText }}rem"
                         x-data="heroSlider({ count: {{ $count }}, seconds: {{ $seconds }} })"
                         @mouseenter="pause()" @mouseleave="resume()"
                         @touchstart.passive="touchStart($event)" @touchend.passive="touchEnd($event)"
                         @keydown.arrow-right="next()" @keydown.arrow-left="prev()"
                         role="region" aria-roledescription="carousel" aria-label="Featured" tabindex="-1">
                    @foreach ($slides as $i => $s)
                        @php
                            $light = ($s['text_colour'] ?? 'light') === 'light';
                            $hasLink = filled($s['link'] ?? null);
                            $hasText = filled($s['eyebrow'] ?? null) || filled($s['heading'] ?? null) || (filled($s['button_text'] ?? null) && $hasLink);
                        @endphp
                        {{-- Shown/hidden only via the "is-current" class (CSS .slide-item), so nothing can override it --}}
                        <div class="slide-item {{ $isSlide ? 'is-sliding' : 'is-fading' }} {{ $i === 0 ? 'is-current' : '' }} absolute inset-0 {{ $phoneTop ? 'max-md:top-16' : '' }} {{ $phoneBottom ? 'max-md:bottom-8' : '' }} overflow-hidden"
                             data-slide="{{ $i }}"
                             :class="{ 'is-current': index === {{ $i }} }"
                             style="--pos: {{ $i }}" :style="`--pos: ${ {{ $i }} - index }`"
                             role="group" aria-roledescription="slide" aria-label="{{ $i + 1 }} of {{ $count }}" :aria-hidden="(index !== {{ $i }}).toString()">
                            <div class="absolute inset-0 max-md:bottom-[var(--phone-text)] {{ $isSlide ? '' : 'slide-zoom' }}" :class="index === {{ $i }} && 'is-active'" style="--slide-seconds: {{ $seconds + 2 }}s">
                                @if (($s['media'] ?? 'image') === 'video' && filled($s['video'] ?? null))
                                    {{-- Slide 1 buffers now, slide 2 fetches metadata; the rest wait until they are next (heroSlider.prepare) --}}
                                    <video muted loop playsinline preload="{{ $i === 0 ? 'auto' : ($i === 1 ? 'metadata' : 'none') }}" @if (! $i) autoplay @endif
                                           class="absolute inset-0 w-full h-full object-cover max-md:object-contain max-md:object-top"
                                           @if (filled($s['image'] ?? null)) poster="{{ Img::url($s['image'], 1600) }}" @endif>
                                        <source src="{{ Storage::disk('public')->url($s['video']) }}" type="{{ str_ends_with($s['video'], '.webm') ? 'video/webm' : 'video/mp4' }}">
                                    </video>
                                @else
                                    <picture>
                                        @if (filled($s['mobile_image'] ?? null))
                                            <source media="(max-width: 767px)" srcset="{{ Img::srcset($s['mobile_image'], [400, 800, 1200]) }}" sizes="100vw">
                                        @endif
                                        <img src="{{ Img::url($s['image'], 1600) }}" srcset="{{ Img::srcset($s['image'], [800, 1200, 1600, 2400]) }}" sizes="100vw"
                                             alt="{{ $s['heading'] ?? '' }}" class="absolute inset-0 w-full h-full object-cover max-md:object-contain max-md:object-top" draggable="false"
                                             {{-- Slide 1 first; slide 2 and the last slide (for "previous") straight after; the rest load when they are about to show --}}
                                             decoding="async" @if (! $i) fetchpriority="high" @elseif ($i === 1 || $i === $count - 1) loading="eager" fetchpriority="low" @else loading="lazy" fetchpriority="low" @endif>
                                    </picture>
                                @endif
                            </div>
                            @if ($light)
                                {{-- Phones keep text below the picture, so the picture isn't darkened --}}
                                <div class="absolute inset-0 bg-gradient-to-t from-black/50 via-black/5 to-black/25 pointer-events-none max-md:hidden"></div>
                            @endif
                            <div class="absolute inset-x-0 bottom-0 gutter pb-2 md:pb-28 max-md:text-day {{ $light ? 'text-day' : 'text-night' }}" data-slide-text>
                                <div class="max-w-5xl">
                                    @if (filled($s['eyebrow'] ?? null))
                                        <p class="eyebrow mb-1 md:mb-5" data-reveal style="--d: 200ms">{{ $s['eyebrow'] }}</p>
                                    @endif
                                    @if (filled($s['heading'] ?? null))
                                        <{{ $first && ! $i ? 'h1' : 'h2' }} class="display max-md:text-[24px] max-md:leading-tight max-md:truncate" data-split data-delay="300">{{ $s['heading'] }}</{{ $first && ! $i ? 'h1' : 'h2' }}>
                                    @endif
                                    @if (filled($s['button_text'] ?? null) && $hasLink)
                                        <div class="mt-2 md:mt-9" data-reveal style="--d: 650ms">
                                            <a href="{{ $s['link'] }}" class="{{ $light ? 'btn-light' : 'btn-dark-outline max-md:border-day max-md:text-day' }} px-10 max-md:h-9 max-md:px-5" :tabindex="index === {{ $i }} ? 0 : -1">{{ $s['button_text'] }}</a>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach

                    @if ($count > 1)
                        {{-- Line indicators: each fills up while its slide is showing --}}
                        <div class="absolute z-20 bottom-0.5 md:bottom-10 inset-x-0 gutter flex items-center gap-6">
                            <div class="flex {{ $count > 8 ? 'gap-1' : 'gap-2' }} flex-1 {{ $count > 6 ? 'max-w-2xl' : 'max-w-md' }}">
                                @foreach ($slides as $i => $s)
                                    <button type="button" @click="go({{ $i }})" class="group relative flex-1 py-3" aria-label="Show slide {{ $i + 1 }}" :aria-current="(index === {{ $i }}).toString()">
                                        <span class="block h-[2px] bg-day/35 overflow-hidden">
                                            <span class="block h-full bg-day origin-left slide-bar" x-ref="bar{{ $i }}"
                                                  :class="{ 'is-running': index === {{ $i }} && running, 'is-done': {{ $i }} < index }"
                                                  :style="`animation-duration: ${seconds}s; animation-play-state: ${paused ? 'paused' : 'running'}`"></span>
                                        </span>
                                        <span class="absolute left-0 -top-4 eyebrow text-[10px] text-day/0 group-hover:text-day/80 transition-colors">{{ str_pad($i + 1, 2, '0', STR_PAD_LEFT) }}</span>
                                    </button>
                                @endforeach
                            </div>
                            <div class="hidden md:flex gap-2 ml-auto">
                                <button type="button" @click="prev()" aria-label="Previous slide"
                                        class="w-11 h-11 grid place-items-center border border-day/40 text-day hover:bg-day hover:text-night transition-colors duration-300">←</button>
                                <button type="button" @click="next()" aria-label="Next slide"
                                        class="w-11 h-11 grid place-items-center border border-day/40 text-day hover:bg-day hover:text-night transition-colors duration-300">→</button>
                            </div>
                        </div>
                    @endif
                </section>
                @endif
                @break

            {{-- ───── Large banner ───── --}}
            @case('hero')
                @php
                    $height = ['full' => 'h-[100svh] min-h-[560px]', 'large' => 'h-[78svh] min-h-[480px]', 'medium' => 'h-[56svh] min-h-[380px]'][$d['height'] ?? 'full'];
                    $light = ($d['text_colour'] ?? 'light') === 'light';
                    $hasLink = filled($d['link'] ?? null);
                @endphp
                <section class="relative {{ $height }} overflow-hidden bg-night {{ $first ? '' : 'section-mt' }}">
                    <div class="absolute inset-0 overflow-hidden" @unless ($first) data-reveal="mask" @endunless>
                        <div class="absolute inset-[-8%_0]" data-parallax="0.18">
                            @if (($d['media'] ?? 'image') === 'video' && filled($d['video'] ?? null))
                                <video autoplay muted loop playsinline preload="metadata" class="absolute inset-0 w-full h-full object-cover {{ $first ? 'kenburns' : '' }}"
                                       @if (filled($d['image'] ?? null)) poster="{{ Img::url($d['image'], 1600) }}" @endif>
                                    <source src="{{ Storage::disk('public')->url($d['video']) }}" type="{{ str_ends_with($d['video'], '.webm') ? 'video/webm' : 'video/mp4' }}">
                                </video>
                            @elseif (filled($d['image'] ?? null))
                                <picture>
                                    @if (filled($d['mobile_image'] ?? null))
                                        <source media="(max-width: 767px)" srcset="{{ Img::srcset($d['mobile_image'], [400, 800, 1200]) }}" sizes="100vw">
                                    @endif
                                    <img src="{{ Img::url($d['image'], 1600) }}" srcset="{{ Img::srcset($d['image'], [800, 1200, 1600, 2400]) }}" sizes="100vw"
                                         alt="{{ $d['heading'] ?? '' }}" class="absolute inset-0 w-full h-full object-cover {{ $first ? 'kenburns' : '' }}"
                                         @if ($first) fetchpriority="high" @else loading="lazy" @endif>
                                </picture>
                            @endif
                        </div>
                    </div>
                    @if ($light)
                        <div class="absolute inset-0 bg-gradient-to-t from-black/50 via-black/5 to-black/25 pointer-events-none"></div>
                    @endif
                    <div class="absolute inset-x-0 bottom-0 gutter pb-12 md:pb-20 {{ $light ? 'text-day' : 'text-night' }}">
                        <div class="max-w-5xl">
                            @if (filled($d['eyebrow'] ?? null))
                                <p class="eyebrow mb-5" data-reveal style="--d: 200ms">{{ $d['eyebrow'] }}</p>
                            @endif
                            @if (filled($d['heading'] ?? null))
                                <{{ $first ? 'h1' : 'h2' }} class="display" data-split data-delay="300">{{ $d['heading'] }}</{{ $first ? 'h1' : 'h2' }}>
                            @endif
                            @if (filled($d['button_text'] ?? null) && $hasLink)
                                <div class="mt-9" data-reveal style="--d: 700ms">
                                    <a href="{{ $d['link'] }}" class="{{ $light ? 'btn-light' : 'btn-dark-outline' }} px-10">{{ $d['button_text'] }}</a>
                                </div>
                            @endif
                        </div>
                    </div>
                    @if ($first)
                        <div class="absolute bottom-6 right-6 md:right-10 hidden md:flex items-center gap-3 eyebrow text-[10px] {{ $light ? 'text-day/80' : 'text-night/70' }}" data-reveal style="--d: 1000ms">
                            Scroll <span class="block w-10 h-px bg-current origin-left animate-pulse"></span>
                        </div>
                    @endif
                </section>
                @break

            {{-- ───── Two banners ───── --}}
            @case('double_banner')
                <section class="grid md:grid-cols-2 gap-px bg-night {{ $first ? '' : 'section-mt' }}">
                    @foreach (['left', 'right'] as $i => $side)
                        <a href="{{ $d[$side.'_link'] ?? '#' }}" class="group relative block overflow-hidden h-[80svh] md:h-[100svh] min-h-[480px]">
                            <div class="absolute inset-0" data-reveal="mask" style="--d: {{ $i * 150 }}ms">
                                <div class="absolute inset-[-8%_0]" data-parallax="{{ $i ? 0.22 : 0.12 }}">
                                    @if (filled($d[$side.'_image'] ?? null))
                                        <img src="{{ Img::url($d[$side.'_image'], 1200) }}" srcset="{{ Img::srcset($d[$side.'_image'], [400, 800, 1200, 1600]) }}"
                                             sizes="(min-width: 768px) 50vw, 100vw" alt="{{ $d[$side.'_title'] ?? '' }}"
                                             class="absolute inset-0 w-full h-full object-cover transition-transform duration-[1600ms] ease-[var(--ease-out-quint)] group-hover:scale-[1.04]"
                                             @if ($first) fetchpriority="high" @else loading="lazy" @endif>
                                    @endif
                                </div>
                            </div>
                            <div class="absolute inset-0 bg-gradient-to-t from-black/45 via-transparent to-transparent"></div>
                            <div class="absolute left-5 right-5 bottom-8 md:left-10 md:bottom-12 text-day">
                                <p class="h1" data-split data-delay="{{ 300 + $i * 150 }}">{{ $d[$side.'_title'] ?? '' }}</p>
                                <p class="label mt-4 inline-flex items-center gap-3" data-reveal style="--d: {{ 600 + $i * 150 }}ms">
                                    <span class="link-draw">Discover</span>
                                    <span class="inline-block transition-transform duration-500 group-hover:translate-x-1.5">→</span>
                                </p>
                            </div>
                        </a>
                    @endforeach
                </section>
                @break

            {{-- ───── Product row (drag to scroll) ───── --}}
            @case('products')
                <section class="section" data-rail-group>
                    @include('shop.partials.section-head', [
                        'title' => $d['title'],
                        'link' => $d['link'] ?? null,
                        'linkText' => $d['link_text'] ?? null,
                        'arrows' => true,
                    ])
                    <div class="relative">
                        <div class="rail edge" data-rail data-stagger="80">
                            @foreach ($d['products'] as $product)
                                <div class="rail-card">
                                    @include('shop.partials.product-card', ['sizes' => '(min-width: 1440px) 340px, (min-width: 1280px) 24vw, (min-width: 768px) 31vw, 72vw'])
                                </div>
                            @endforeach
                        </div>
                        <div class="edge"><div class="h-px bg-line relative overflow-hidden"><span data-rail-progress class="absolute inset-y-0 left-0 bg-ink transition-transform duration-150"></span></div></div>
                    </div>
                </section>
                @break

            {{-- ───── Image tiles ───── --}}
            @case('tiles')
                @php $n = (int) ($d['columns'] ?? 4); $cols = [2 => 'md:grid-cols-2', 3 => 'md:grid-cols-3', 4 => 'md:grid-cols-4'][$n] ?? 'md:grid-cols-4'; @endphp
                <section class="section">
                    @if (filled($d['title'] ?? null))
                        @include('shop.partials.section-head', ['title' => $d['title']])
                    @endif
                    <div class="edge grid grid-cols-2 {{ $cols }} gap-1 md:gap-2">
                        @foreach ($d['tiles'] ?? [] as $i => $tile)
                            <a href="{{ $tile['link'] ?? '#' }}" class="group relative block aspect-[4/5] overflow-hidden bg-paper-2" data-reveal="mask" style="--d: {{ $i * 90 }}ms">
                                @if (filled($tile['image'] ?? null))
                                    <img src="{{ Img::url($tile['image'], 800) }}" srcset="{{ Img::srcset($tile['image'], [400, 800, 1200]) }}"
                                         sizes="(min-width: 768px) {{ (int) (100 / max(1, $n)) }}vw, 50vw" alt="" loading="lazy"
                                         class="absolute inset-0 w-full h-full object-cover transition-transform duration-[1600ms] ease-[var(--ease-out-quint)] group-hover:scale-[1.05]">
                                @endif
                                <div class="absolute inset-0 bg-gradient-to-t from-black/40 via-transparent to-transparent opacity-80 group-hover:opacity-100 transition-opacity duration-500"></div>
                                <span class="absolute bottom-4 left-4 right-4 text-day flex justify-between items-end">
                                    <span class="h3 text-[16px] md:text-[18px]">{{ $tile['title'] ?? '' }}</span>
                                    <span class="transition-transform duration-500 group-hover:translate-x-1">→</span>
                                </span>
                            </a>
                        @endforeach
                    </div>
                </section>
                @break

            {{-- ───── Text ───── --}}
            @case('text')
                @php $center = ($d['align'] ?? 'center') === 'center'; @endphp
                <section class="gutter section">
                    <div class="max-w-4xl {{ $center ? 'mx-auto text-center' : '' }}">
                        @if (filled($d['heading'] ?? null))<p class="eyebrow text-mute mb-6" data-reveal>{{ $d['heading'] }}</p>@endif
                        @if (filled($d['body'] ?? null))<p class="h2" data-split>{{ $d['body'] }}</p>@endif
                        @if (filled($d['button_text'] ?? null) && filled($d['link'] ?? null))
                            <a href="{{ $d['link'] }}" class="inline-block label link-u mt-10" data-reveal style="--d: 300ms">{{ $d['button_text'] }}</a>
                        @endif
                    </div>
                </section>
                @break

            {{-- ───── About / Who we are ───── --}}
            @case('about')
                <section class="grid lg:grid-cols-2 bg-paper-2 text-ink {{ $first ? '' : 'section-mt' }}">
                    <div class="flex flex-col justify-center gutter py-12 md:py-20 xl:px-16">
                        <h2 class="section-title" data-reveal>{{ $d['heading'] ?? '' }}</h2>
                        <p class="mt-5 md:mt-7 text-[15px] md:text-[17px] leading-relaxed text-ink-2 whitespace-pre-line md:text-justify" data-reveal style="--d: 150ms">{{ $d['body'] ?? '' }}</p>
                    </div>
                    {{-- The whole picture always shows; on phones and tablets the frame takes the picture's own shape --}}
                    <div class="relative max-lg:aspect-[var(--about-ratio)] lg:min-h-[560px] overflow-hidden" data-reveal="fade"
                         style="--about-ratio: {{ number_format(Img::ratio($d['image'] ?? null) ?? 4 / 3, 4, '.', '') }}">
                        @if (filled($d['image'] ?? null))
                            <img src="{{ Img::url($d['image'], 1200) }}" srcset="{{ Img::srcset($d['image'], [800, 1200, 1600, 2400]) }}"
                                 sizes="(min-width: 1024px) 50vw, 100vw" alt="{{ $d['heading'] ?? '' }}" loading="lazy"
                                 class="absolute inset-0 w-full h-full object-contain">
                        @endif
                    </div>
                </section>
                @break

            {{-- ───── What we offer (checklist) ───── --}}
            @case('offer')
                <section class="bg-paper-2 text-ink py-12 md:py-16 {{ $first ? '' : 'section-mt' }}">
                    <h2 class="section-title text-center gutter" data-reveal>{{ $d['heading'] ?? '' }}</h2>
                    <ul class="edge mt-8 md:mt-10 grid sm:grid-cols-2 lg:grid-cols-3 gap-x-8 gap-y-4 md:gap-y-5" data-stagger="40">
                        @foreach (array_filter($d['items'] ?? [], 'filled') as $item)
                            <li class="flex items-start gap-3 text-[15px] md:text-[17px] leading-snug text-ink-2">
                                <svg class="w-6 h-6 shrink-0 text-ink" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="m8 12 3 3 5-6"/></svg>
                                <span class="pt-px">{{ $item }}</span>
                            </li>
                        @endforeach
                    </ul>
                </section>
                @break
        @endswitch

        @if ($loop->index === $stripAfter)
            @include('shop.partials.trust-strip')
        @endif
    @endforeach
@endsection

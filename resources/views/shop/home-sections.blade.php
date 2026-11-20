@extends('shop.layout')
@php use App\Support\Img; @endphp

{{-- Header floats over the first banner --}}
@if (in_array($sections[0]['type'] ?? null, ['hero', 'double_banner'], true))
    @section('header-overlay', '1')
@endif

@section('content')
    @foreach ($sections as $section)
        @php $d = $section['data'] ?? []; $first = $loop->first; @endphp

        @switch($section['type'])

            {{-- ───── Large banner ───── --}}
            @case('hero')
                @php
                    $height = ['full' => 'h-[100svh] min-h-[560px]', 'large' => 'h-[78svh] min-h-[480px]', 'medium' => 'h-[56svh] min-h-[380px]'][$d['height'] ?? 'full'];
                    $light = ($d['text_colour'] ?? 'light') === 'light';
                    $hasLink = filled($d['link'] ?? null);
                @endphp
                <section class="relative {{ $height }} overflow-hidden bg-ink {{ $first ? '' : 'mt-24' }}">
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
                    <div class="absolute inset-x-0 bottom-0 gutter pb-12 md:pb-20 {{ $light ? 'text-paper' : 'text-ink' }}">
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
                        <div class="absolute bottom-6 right-6 md:right-10 hidden md:flex items-center gap-3 eyebrow text-[10px] {{ $light ? 'text-paper/80' : 'text-ink/70' }}" data-reveal style="--d: 1000ms">
                            Scroll <span class="block w-10 h-px bg-current origin-left animate-pulse"></span>
                        </div>
                    @endif
                </section>
                @break

            {{-- ───── Two banners ───── --}}
            @case('double_banner')
                <section class="grid md:grid-cols-2 gap-px bg-ink {{ $first ? '' : 'mt-24' }}">
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
                            <div class="absolute left-5 right-5 bottom-8 md:left-10 md:bottom-12 text-paper">
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
                <section class="pt-24">
                    <div class="gutter flex justify-between items-end mb-8">
                        <h2 class="h1" data-split>{{ $d['title'] }}</h2>
                        @if ($d['link'])
                            <a href="{{ $d['link'] }}" class="label link-u shrink-0 mb-2" data-reveal>{{ $d['link_text'] ?: 'Shop all' }}</a>
                        @endif
                    </div>
                    <div class="relative">
                        <div class="rail gutter" data-rail data-stagger="80">
                            @foreach ($d['products'] as $product)
                                <div class="snap-start shrink-0 w-[72vw] sm:w-[44vw] md:w-[31vw] xl:w-[23.5vw]">
                                    @include('shop.partials.product-card', ['sizes' => '(min-width: 1280px) 24vw, (min-width: 768px) 31vw, 72vw'])
                                </div>
                            @endforeach
                        </div>
                        <div class="gutter"><div class="h-px bg-line relative overflow-hidden"><span data-rail-progress class="absolute inset-y-0 left-0 bg-ink transition-transform duration-150"></span></div></div>
                    </div>
                </section>
                @break

            {{-- ───── Image tiles ───── --}}
            @case('tiles')
                @php $n = (int) ($d['columns'] ?? 4); $cols = [2 => 'md:grid-cols-2', 3 => 'md:grid-cols-3', 4 => 'md:grid-cols-4'][$n] ?? 'md:grid-cols-4'; @endphp
                <section class="gutter pt-24">
                    @if (filled($d['title'] ?? null))<h2 class="h1 mb-8" data-split>{{ $d['title'] }}</h2>@endif
                    <div class="grid grid-cols-2 {{ $cols }} gap-1 md:gap-2">
                        @foreach ($d['tiles'] ?? [] as $i => $tile)
                            <a href="{{ $tile['link'] ?? '#' }}" class="group relative block aspect-[4/5] overflow-hidden bg-paper-2" data-reveal="mask" style="--d: {{ $i * 90 }}ms">
                                @if (filled($tile['image'] ?? null))
                                    <img src="{{ Img::url($tile['image'], 800) }}" srcset="{{ Img::srcset($tile['image'], [400, 800, 1200]) }}"
                                         sizes="(min-width: 768px) {{ (int) (100 / max(1, $n)) }}vw, 50vw" alt="" loading="lazy"
                                         class="absolute inset-0 w-full h-full object-cover transition-transform duration-[1600ms] ease-[var(--ease-out-quint)] group-hover:scale-[1.05]">
                                @endif
                                <div class="absolute inset-0 bg-gradient-to-t from-black/40 via-transparent to-transparent opacity-80 group-hover:opacity-100 transition-opacity duration-500"></div>
                                <span class="absolute bottom-4 left-4 right-4 text-paper flex justify-between items-end">
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
                <section class="gutter pt-32 pb-8">
                    <div class="max-w-4xl {{ $center ? 'mx-auto text-center' : '' }}">
                        @if (filled($d['heading'] ?? null))<p class="eyebrow text-mute mb-6" data-reveal>{{ $d['heading'] }}</p>@endif
                        @if (filled($d['body'] ?? null))<p class="h2" data-split>{{ $d['body'] }}</p>@endif
                        @if (filled($d['button_text'] ?? null) && filled($d['link'] ?? null))
                            <a href="{{ $d['link'] }}" class="inline-block label link-u mt-10" data-reveal style="--d: 300ms">{{ $d['button_text'] }}</a>
                        @endif
                    </div>
                </section>
                @break
        @endswitch
    @endforeach
@endsection

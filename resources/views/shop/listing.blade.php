@extends('shop.layout')
@php use App\Support\Img; @endphp

@section('title', $seoTitle ?? $title)
@if (! empty($seoDescription ?? $description))
    @section('description', \Illuminate\Support\Str::limit(strip_tags($seoDescription ?? $description), 155))
@endif
@if ($banner)
    @section('header-overlay', '1')
@endif

@section('content')
    @if ($banner)
        <section class="relative h-[70svh] min-h-[420px] overflow-hidden bg-night">
            <div class="absolute inset-[-8%_0]" data-parallax="0.18">
                <img src="{{ Img::url($banner, 1600) }}" srcset="{{ Img::srcset($banner, [800, 1200, 1600, 2400]) }}" sizes="100vw"
                     alt="" fetchpriority="high" class="kenburns absolute inset-0 w-full h-full object-cover">
            </div>
            <div class="absolute inset-0 bg-gradient-to-t from-black/50 to-black/10"></div>
            <div class="absolute inset-x-0 bottom-0 edge pb-12 text-day">
                <h1 class="display" data-split data-delay="200">{{ $heading }}</h1>
            </div>
        </section>
    @endif

    <div x-data="listing">
        {{-- Title --}}
        @unless ($banner)
            <header class="edge pt-10 md:pt-12 pb-6 {{ $headerImage ? 'relative overflow-hidden bg-night text-day' : '' }}">
                @if ($headerImage)
                    {{-- Fills the band behind the title; the band keeps its normal height. --}}
                    <img src="{{ Img::url($headerImage, 1600) }}" srcset="{{ Img::srcset($headerImage, [800, 1200, 1600, 2400]) }}" sizes="100vw"
                         alt="" fetchpriority="high" class="absolute inset-0 w-full h-full object-cover">
                    <div class="absolute inset-0 bg-gradient-to-r from-black/45 via-black/15 to-transparent"></div>
                @endif
                <div class="{{ $headerImage ? 'relative' : '' }}">
                @if (count($breadcrumbs) > 1)
                    <nav class="eyebrow {{ $headerImage ? 'text-day/80' : 'text-mute' }} mb-5 flex flex-wrap gap-x-2" aria-label="Breadcrumb" data-reveal>
                        @foreach ($breadcrumbs as $crumb)
                            @if (! $loop->last)<a href="{{ $crumb['url'] }}" class="link-draw">{{ $crumb['name'] }}</a><span>/</span>@endif
                        @endforeach
                    </nav>
                @endif
                <h1 class="h1" data-split>{{ $heading }}</h1>
                @if ($description)
                    <p class="lead max-w-2xl mt-5 {{ $headerImage ? 'text-day/85' : 'text-mute' }}" data-reveal style="--d: 200ms">{{ $description }}</p>
                @endif
                </div>
            </header>
        @endunless

        {{-- Toolbar: sub-categories, count, grid size, filter (stays in place; scrolls with the page) --}}
        <div class="relative bg-paper border-y border-line">
            <div class="edge h-12 flex items-center gap-6">
                <div class="flex-1 min-w-0 flex gap-5 overflow-x-auto label whitespace-nowrap [scrollbar-width:none]">
                    @foreach ($chips as $chip)
                        <a href="{{ route('shop.category', $chip) }}" class="link-draw {{ $current && $current->id === $chip->id ? 'is-active' : 'text-mute hover:text-ink' }}">{{ $chip->name }}</a>
                    @endforeach
                    @if ($chips->isEmpty())
                        <span class="text-mute">{{ $products->total() }} {{ \Illuminate\Support\Str::plural('item', $products->total()) }}</span>
                    @endif
                </div>
                @if ($chips->isNotEmpty())
                    <span class="label text-mute hidden sm:inline">{{ $products->total() }} {{ \Illuminate\Support\Str::plural('item', $products->total()) }}</span>
                @endif
                <div class="hidden md:flex items-center gap-2" role="group" aria-label="Products per row">
                    @foreach ([2, 3, 4] as $n)
                        <button type="button" @click="setCols({{ $n }})" class="p-1 transition-opacity" :class="cols === {{ $n }} ? 'opacity-100' : 'opacity-30 hover:opacity-70'" aria-label="{{ $n }} per row">
                            <span class="flex gap-[2px]">@for ($i = 0; $i < $n; $i++)<span class="w-[5px] h-3 bg-ink"></span>@endfor</span>
                        </button>
                    @endforeach
                </div>
                @if ($products->total() || $selectedSizes || $selectedColours)
                    <button class="label link-draw" @click="filters = true">
                        Filter &amp; sort{{ ($n = count($selectedSizes) + count($selectedColours)) ? " ($n)" : '' }}
                    </button>
                @endif
            </div>
        </div>

        {{-- Grid --}}
        @if ($products->isEmpty())
            <div class="edge py-8 md:py-10"><div class="bg-paper-2 px-6 py-14 md:py-20 text-center">
                <p class="h2 mb-3" data-reveal>No products found</p>
                <p class="text-mute mb-8" data-reveal style="--d: 120ms">Try removing a filter, or explore something new.</p>
                <div class="flex justify-center gap-3" data-reveal style="--d: 200ms">
                    @if ($selectedSizes || $selectedColours)
                        <a href="{{ url()->current() }}{{ $searchTerm ? '?q='.urlencode($searchTerm) : '' }}" class="btn-dark-outline">Clear filters</a>
                    @endif
                    <a href="{{ route('shop.home') }}" class="btn-dark-outline">Go to home</a>
                </div>
            </div></div>
        @else
            <div class="edge pt-2">
                <div x-ref="grid" data-grid data-stagger="60"
                     class="grid grid-cols-2 gap-x-1 md:gap-x-2 transition-[grid-template-columns] duration-500"
                     :class="{ 'md:grid-cols-2': cols === 2, 'md:grid-cols-3': cols === 3, 'md:grid-cols-3 xl:grid-cols-4': cols === 4 }">
                    @foreach ($products as $product)
                        @include('shop.partials.product-card')
                    @endforeach
                </div>
                @if ($products->hasMorePages())
                    <div class="max-w-xs mx-auto pt-10 text-center" data-more>
                        <p class="text-mute text-[12px] mb-4">Showing {{ $products->lastItem() }} of {{ $products->total() }}</p>
                        <div class="h-px bg-line mb-6 relative"><span class="absolute inset-y-0 left-0 bg-ink" style="width: {{ round($products->lastItem() / max(1, $products->total()) * 100) }}%"></span></div>
                        <a href="{{ $products->nextPageUrl() }}" class="btn-line" @click.prevent="more($el.href)" :class="loadingMore && 'opacity-60 pointer-events-none'">
                            <span x-text="loadingMore ? 'Loading…' : 'Load more'">Load more</span>
                        </a>
                    </div>
                @endif
            </div>
        @endif

        {{-- Filter drawer --}}
        <template x-teleport="body">
            <div x-show="filters" x-cloak class="fixed inset-0 z-[90]" @keydown.escape.window="filters = false"
                 x-effect="document.documentElement.style.overflow = filters ? 'hidden' : ''">
                <div class="scrim" @click="filters = false" x-show="filters" x-transition.opacity.duration.400ms></div>
                <form method="get" action="{{ url()->current() }}" class="drawer"
                      x-show="filters" x-transition:enter="transition duration-500 ease-[var(--ease-out-quint)]" x-transition:enter-start="translate-x-full"
                      x-transition:leave="transition duration-300" x-transition:leave-end="translate-x-full">
                    @if ($searchTerm)<input type="hidden" name="q" value="{{ $searchTerm }}">@endif
                    <div class="drawer-head">
                        <span class="label">Filter &amp; sort</span>
                        <button type="button" class="label link-draw" @click="filters = false">Close</button>
                    </div>
                    <div class="flex-1 overflow-y-auto px-6 py-6 space-y-2" data-lenis-prevent>
                        <details class="border-b border-line pb-5" open x-data="{ o: true }">
                            <summary class="label cursor-pointer list-none flex justify-between py-2">Sort by <span>+</span></summary>
                            <div class="pt-2 space-y-2.5">
                                @foreach (['featured' => 'Recommended', 'newest' => 'Newest'] as $value => $label)
                                    <label class="flex items-center gap-3 cursor-pointer">
                                        <input type="radio" name="sort" value="{{ $value }}" @checked($sort === $value) class="radio"> {{ $label }}
                                    </label>
                                @endforeach
                            </div>
                        </details>
                        @if ($sizes->isNotEmpty())
                            <details class="border-b border-line pb-5" open>
                                <summary class="label cursor-pointer list-none flex justify-between py-2">Size <span>+</span></summary>
                                <div class="pt-2 flex flex-wrap gap-1.5">
                                    @foreach ($sizes as $size)
                                        <label class="cursor-pointer">
                                            <input type="checkbox" name="size[]" value="{{ $size }}" @checked(in_array($size, $selectedSizes)) class="peer sr-only">
                                            <span class="chip peer-checked:bg-ink peer-checked:text-paper peer-checked:border-ink peer-focus-visible:outline">{{ $size }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </details>
                        @endif
                        @if ($colours->isNotEmpty())
                            <details class="pb-5" open>
                                <summary class="label cursor-pointer list-none flex justify-between py-2">Colour <span>+</span></summary>
                                <div class="pt-2 space-y-2.5">
                                    @foreach ($colours as $c)
                                        <label class="flex items-center gap-3 cursor-pointer">
                                            <input type="checkbox" name="colour[]" value="{{ $c }}" @checked(in_array($c, $selectedColours)) class="check"> {{ $c }}
                                        </label>
                                    @endforeach
                                </div>
                            </details>
                        @endif
                    </div>
                    <div class="p-6 border-t border-line grid grid-cols-2 gap-2">
                        <a href="{{ url()->current() }}{{ $searchTerm ? '?q='.urlencode($searchTerm) : '' }}" class="btn-line">Clear all</a>
                        <button class="btn-black">Show results</button>
                    </div>
                </form>
            </div>
        </template>
    </div>
@endsection

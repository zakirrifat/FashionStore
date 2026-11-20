@extends('shop.layout')
@php use App\Support\Img; @endphp

{{-- Automatic home page (used until sections are built in Admin › Content › Home page) --}}
@if ($hero && $hero->colours->first()?->images->first())
    @section('header-overlay', '1')
@endif

@section('content')
    @if ($hero && ($heroImage = $hero->colours->first()?->images->first()))
        <section class="relative h-[100svh] min-h-[560px] overflow-hidden bg-ink">
            <div class="absolute inset-[-8%_0]" data-parallax="0.18">
                <img src="{{ $heroImage->url(1600) }}" srcset="{{ Img::srcset($heroImage->path, [800, 1200, 1600, 2400]) }}" sizes="100vw"
                     alt="{{ $heroImage->alt ?: $hero->name }}" fetchpriority="high" class="kenburns absolute inset-0 w-full h-full object-cover">
            </div>
            <div class="absolute inset-0 bg-gradient-to-t from-black/55 via-black/5 to-black/25"></div>
            <div class="absolute inset-x-0 bottom-0 gutter pb-12 md:pb-20 text-paper">
                <p class="eyebrow mb-5" data-reveal style="--d: 200ms">New season</p>
                <h1 class="display max-w-5xl" data-split data-delay="300">{{ $hero->name }}</h1>
                <div class="mt-9" data-reveal style="--d: 700ms"><a href="{{ $hero->url() }}" class="btn-light px-10">Shop now</a></div>
            </div>
        </section>
    @else
        <section class="gutter pt-40 pb-24 text-center">
            <p class="eyebrow text-mute mb-6" data-reveal>Welcome</p>
            <h1 class="display" data-split>{{ config('app.name') }}</h1>
            <p class="lead mt-6" data-reveal style="--d: 400ms">New collection coming soon.</p>
        </section>
    @endif

    @if ($latest->isNotEmpty())
        <section class="pt-24">
            <div class="gutter flex justify-between items-end mb-8">
                <h2 class="h1" data-split>New arrivals</h2>
                <a href="{{ route('shop.collection', 'new-arrivals') }}" class="label link-u shrink-0 mb-2" data-reveal>Shop all</a>
            </div>
            <div class="relative">
                <div class="rail gutter" data-rail data-stagger="80">
                    @foreach ($latest as $product)
                        <div class="snap-start shrink-0 w-[72vw] sm:w-[44vw] md:w-[31vw] xl:w-[23.5vw]">
                            @include('shop.partials.product-card', ['sizes' => '(min-width: 1280px) 24vw, (min-width: 768px) 31vw, 72vw'])
                        </div>
                    @endforeach
                </div>
                <div class="gutter"><div class="h-px bg-line relative overflow-hidden"><span data-rail-progress class="absolute inset-y-0 left-0 bg-ink transition-transform duration-150"></span></div></div>
            </div>
        </section>
    @endif

    @if ($collections->isNotEmpty())
        <section class="gutter pt-24">
            <h2 class="h1 mb-8" data-split>Discover more</h2>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-1 md:gap-2">
                @foreach ($collections as $i => $collection)
                    <a href="{{ route('shop.collection', $collection) }}" class="group relative block aspect-[4/5] overflow-hidden bg-paper-2" data-reveal="mask" style="--d: {{ $i * 90 }}ms">
                        @if ($collection->image)
                            <img src="{{ Img::url($collection->image, 800) }}" srcset="{{ Img::srcset($collection->image, [400, 800, 1200]) }}" sizes="(min-width: 768px) 25vw, 50vw"
                                 alt="" loading="lazy" class="absolute inset-0 w-full h-full object-cover transition-transform duration-[1600ms] ease-[var(--ease-out-quint)] group-hover:scale-[1.05]">
                            <div class="absolute inset-0 bg-gradient-to-t from-black/40 to-transparent"></div>
                        @else
                            <span class="absolute inset-0 grid place-items-center font-serif text-[28px] text-[#cfcbc1]">{{ $collection->name }}</span>
                        @endif
                        <span class="absolute bottom-4 left-4 right-4 flex justify-between {{ $collection->image ? 'text-paper' : '' }}">
                            <span class="h3">{{ $collection->name }}</span><span class="transition-transform duration-500 group-hover:translate-x-1">→</span>
                        </span>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    @if ($featured->count() > 1)
        <section class="gutter pt-24">
            <h2 class="h1 mb-8" data-split>Featured</h2>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-x-1 md:gap-x-2" data-stagger="70">
                @foreach ($featured as $product)
                    @include('shop.partials.product-card')
                @endforeach
            </div>
        </section>
    @endif
@endsection

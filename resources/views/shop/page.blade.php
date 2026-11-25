@extends('shop.layout')

@section('title', $page->seo_title ?: $page->title)
@if ($page->seo_description)
    @section('description', $page->seo_description)
@endif

@section('content')
    <article class="gutter pt-12 md:pt-16">
        <header class="max-w-4xl mx-auto mb-10 md:mb-12 text-center">
            <p class="eyebrow text-mute mb-4" data-reveal>{{ config('app.name') }}</p>
            <h1 class="h1" data-split>{{ $page->title }}</h1>
        </header>
        @if ($page->isAbout() && filled($page->side_image))
            {{-- Like "Who we are" on the home page: text on the left, picture on the right (below the text on phones) --}}
            <div class="max-w-6xl mx-auto grid lg:grid-cols-2 gap-10 lg:gap-16 items-start">
                <div class="rich rich-editorial rich-split" data-stagger="60">{!! $page->bodyHtml() !!}</div>
                <div class="lg:sticky lg:top-28" data-reveal="fade">
                    <img src="{{ \App\Support\Img::url($page->side_image, 1200) }}"
                         srcset="{{ \App\Support\Img::srcset($page->side_image, [800, 1200, 1600, 2400]) }}"
                         sizes="(min-width: 1024px) 50vw, 100vw" alt="{{ $page->title }}" loading="lazy"
                         class="w-full h-auto rounded-2xl">
                </div>
            </div>
        @elseif ($page->isAbout())
            {{-- Each paragraph, image and quote fades in as you scroll --}}
            <div class="rich rich-editorial max-w-2xl mx-auto" data-stagger="60">{!! $page->bodyHtml() !!}</div>
        @else
            <div class="rich max-w-2xl mx-auto" data-reveal style="--d: 300ms">{!! $page->bodyHtml() !!}</div>
        @endif
    </article>

    @if ($team = $page->teamSection())
        @include('shop.partials.team-section', ['team' => $team])
    @endif
@endsection

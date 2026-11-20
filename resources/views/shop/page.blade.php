@extends('shop.layout')

@section('title', $page->seo_title ?: $page->title)
@if ($page->seo_description)
    @section('description', $page->seo_description)
@endif

@section('content')
    <article class="gutter pt-16 md:pt-28">
        <header class="max-w-5xl mx-auto mb-14 md:mb-20 text-center">
            <p class="eyebrow text-mute mb-6" data-reveal>{{ config('app.name') }}</p>
            <h1 class="display" data-split>{{ $page->title }}</h1>
        </header>
        <div class="rich max-w-2xl mx-auto" data-reveal style="--d: 300ms">{!! $page->bodyHtml() !!}</div>
    </article>
@endsection

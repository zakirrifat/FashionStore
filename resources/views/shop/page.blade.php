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
        <div class="rich max-w-2xl mx-auto" data-reveal style="--d: 300ms">{!! $page->bodyHtml() !!}</div>
    </article>
@endsection

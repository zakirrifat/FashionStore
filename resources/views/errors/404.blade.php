@extends('shop.layout')

@section('title', 'Page not found')

@section('content')
    <section class="gutter min-h-[70vh] grid place-items-center text-center py-24">
        <div>
            <p class="font-serif text-[28vw] md:text-[220px] leading-none tracking-[-0.06em] text-paper-2 select-none" aria-hidden="true" data-reveal>404</p>
            <h1 class="h1 -mt-6 md:-mt-14" data-split data-delay="200">This page has moved on</h1>
            <p class="lead text-mute mt-5 max-w-md mx-auto" data-reveal style="--d: 400ms">The link may be old, or the piece may no longer be available.</p>
            <div class="mt-10 flex flex-wrap justify-center gap-3" data-reveal style="--d: 550ms">
                <a href="{{ route('shop.home') }}" class="btn-black w-auto px-10">Go to home</a>
                <a href="{{ route('shop.search') }}" class="btn-dark-outline px-10">Search</a>
            </div>
        </div>
    </section>
@endsection

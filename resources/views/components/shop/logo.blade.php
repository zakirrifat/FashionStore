@props(['variant' => 'auto', 'textClass' => ''])
{{--
    Store logo. variant: auto (dark, or white over photos — switched by the header), dark, light.
    Falls back to the store name in the headline font when no logo is uploaded.
--}}
@php
    $dark = \App\Support\Brand::url('logo_dark');
    $light = \App\Support\Brand::url('logo_light');
    $name = config('app.name');
@endphp
@if ($dark)
    @if ($variant === 'light')
        <img src="{{ $light }}" alt="{{ $name }}" {{ $attributes->merge(['class' => 'w-auto block']) }}>
    @elseif ($variant === 'dark')
        <img src="{{ $dark }}" alt="{{ $name }}" {{ $attributes->merge(['class' => 'w-auto block']) }}>
    @else
        <span class="relative block">
            <img src="{{ $dark }}" alt="{{ $name }}" {{ $attributes->merge(['class' => 'logo-dark w-auto block transition-opacity duration-500']) }}>
            <img src="{{ $light }}" alt="" aria-hidden="true" {{ $attributes->merge(['class' => 'logo-light w-auto block absolute inset-0 transition-opacity duration-500']) }}>
        </span>
    @endif
@else
    <span class="font-serif tracking-[0.02em] whitespace-nowrap {{ $textClass }}">{{ $name }}</span>
@endif

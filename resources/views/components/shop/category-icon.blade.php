{{--
    Thin line icon for a category, picked from words in its name, so new
    categories created in the admin get a sensible icon automatically.
    Unknown names (and "View all") fall back to a hanger, which echoes the logo.
    Usage: <x-shop.category-icon :name="$category->name" class="w-6 h-6" />
           <x-shop.category-icon icon="hanger" />
    To support a new word, add it to $rules below (first match wins, so
    more specific words come first: "sweatshirt" before "shirt").
--}}
@props(['name' => '', 'icon' => null])
@php
    $rules = [
        'jeans' => ['jean', 'denim'],
        'shorts' => ['short'],
        'sweat' => ['sweat', 'hood'],
        'knit' => ['knit', 'jumper', 'cardigan', 'sweater', 'pullover'],
        'tee' => ['t-shirt', 'tshirt', 'tee', 'top', 'polo'],
        'coat' => ['outer', 'coat', 'parka', 'puffer', 'trench'],
        'blazer' => ['jacket', 'blazer', 'suit', 'waistcoat'],
        'shirt' => ['shirt', 'blouse'],
        'trousers' => ['trouser', 'pant', 'chino', 'legging', 'jogger'],
        'dress' => ['dress', 'gown', 'jumpsuit'],
        'skirt' => ['skirt'],
    ];
    if (! $icon) {
        $n = \Illuminate\Support\Str::lower($name);
        $icon = collect($rules)->search(fn ($words) => \Illuminate\Support\Str::contains($n, $words)) ?: 'hanger';
    }
@endphp
<svg {{ $attributes->merge(['class' => 'w-6 h-6 shrink-0']) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.15" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    @switch($icon)
        @case('jeans')
            <path d="M6.8 3h10.4l1.1 18h-4.4L12 10.2 10.1 21H5.7L6.8 3z"/><path d="M6.7 5.6h10.6"/><path d="M12 5.6v4.6"/><path d="M8.3 5.6c0 1.6 1 2.5 2.4 2.5M15.7 5.6c0 1.6-1 2.5-2.4 2.5"/>
            @break
        @case('trousers')
            <path d="M7.2 3h9.6l1.5 18h-4.2L12 9.8 9.9 21H5.7L7.2 3z"/><path d="M7 5.6h10"/><path d="M9.4 7.5 8.3 19M14.6 7.5l1.1 11.5"/>
            @break
        @case('shorts')
            <path d="M5 5h14l1.3 11.6-6.2 1L12 11.2l-2.1 6.4-6.2-1L5 5z"/><path d="M4.8 7.6h14.4"/><path d="M12 7.6v3.6"/>
            @break
        @case('tee')
            <path d="M9 3.5 4.2 5.8 2.5 10.6l3.1 1.2L7 10.2v10.3h10V10.2l1.4 1.6 3.1-1.2-1.7-4.8L15 3.5c-.5 1.3-1.6 2-3 2s-2.5-.7-3-2z"/>
            @break
        @case('shirt')
            <path d="M9 3.5 4.6 5.4 2.6 14l2.8.7L7 9.6v10.9h10V9.6l1.6 5.1 2.8-.7-2-8.6L15 3.5"/><path d="M9 3.5 12 7l3-3.5"/><path d="M9 3.5 8.4 6.2l1.9 1.6L12 7M15 3.5l.6 2.7-1.9 1.6L12 7"/><path d="M12 7v13.5"/><circle cx="12" cy="10.5" r=".35"/><circle cx="12" cy="13.5" r=".35"/><circle cx="12" cy="16.5" r=".35"/>
            @break
        @case('sweat')
            <path d="M9 3.5 5 5 2.8 16.6h2.7L7.5 9v11.5h9V9l2 7.6h2.7L19 5l-4-1.5c-.6 1.1-1.7 1.7-3 1.7s-2.4-.6-3-1.7z"/><path d="M7.5 18.6h9"/><path d="M3 15.2h2.8M18.2 15.2H21"/>
            @break
        @case('knit')
            <path d="M9 3.5 5 5 2.8 16.6h2.7L7.5 9v11.5h9V9l2 7.6h2.7L19 5l-4-1.5c-.6 1.1-1.7 1.7-3 1.7s-2.4-.6-3-1.7z"/><path d="M7.5 18.6h9"/><path d="M3 15.2h2.8M18.2 15.2H21"/><path d="m9.5 10 1.25 1.4L12 10l1.25 1.4L14.5 10M9.5 13.4l1.25 1.4 1.25-1.4 1.25 1.4 1.25-1.4"/>
            @break
        @case('blazer')
            <path d="M8.6 3.5 4.7 5.3 2.8 18.8l2.6.4L7 10.4v10.1h10V10.4l1.6 8.8 2.6-.4-1.9-13.5-3.9-1.8"/><path d="M8.6 3.5 12 12.5l3.4-9"/><path d="M8.6 3.5 7.9 7.6l1.8 1M15.4 3.5l.7 4.1-1.8 1"/><path d="M12 12.5v8"/><circle cx="12" cy="15.2" r=".4"/><path d="M8.3 16h2M13.7 16h2"/>
            @break
        @case('coat')
            <path d="M8.6 3 4.5 4.9 2.6 18.6l2.6.3L6.8 10v11.5h10.4V10l1.6 8.9 2.6-.3-1.9-13.7L15.4 3"/><path d="M8.6 3 12 8.2 15.4 3"/><path d="M12 8.2v13.3"/><path d="M6.8 13.4h10.4"/><circle cx="13.7" cy="10.4" r=".4"/><circle cx="13.7" cy="16.4" r=".4"/>
            @break
        @case('dress')
            <path d="M9.5 3v3.3C8.5 7.2 8 8.6 8 11h8c0-2.4-.5-3.8-1.5-4.7V3"/><path d="M8 11 4.6 21h14.8L16 11"/><path d="M9.6 6.3h4.8"/>
            @break
        @case('skirt')
            <path d="M7.5 5h9l3.4 14.4c-2.3.9-5 1.3-7.9 1.3s-5.6-.4-7.9-1.3L7.5 5z"/><path d="M7.1 7.5h9.8"/><path d="M10.2 7.5 9.2 20.4M13.8 7.5l1 12.9"/>
            @break
        @default
            <path d="M12 8.2V7a2 2 0 1 0-2-2"/><path d="M12 8.2 3.3 14.4a.9.9 0 0 0 .5 1.6h16.4a.9.9 0 0 0 .5-1.6L12 8.2z"/>
    @endswitch
</svg>

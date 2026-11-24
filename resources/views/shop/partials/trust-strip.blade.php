{{-- Fixed selling points under the home page banner. Icons are inline, so no uploads are needed. --}}
@php
    $points = [
        ['100% Genuine', 'Branded Stock', '<path d="M3.85 8.62a4 4 0 0 1 4.78-4.77 4 4 0 0 1 6.74 0 4 4 0 0 1 4.78 4.78 4 4 0 0 1 0 6.74 4 4 0 0 1-4.77 4.78 4 4 0 0 1-6.75 0 4 4 0 0 1-4.78-4.77 4 4 0 0 1 0-6.76Z"/><path d="m9 12 2 2 4-4"/>'],
        ['Competitive', 'Wholesale Prices', '<path d="M12.586 2.586A2 2 0 0 0 11.172 2H4a2 2 0 0 0-2 2v7.172a2 2 0 0 0 .586 1.414l8.704 8.704a2.426 2.426 0 0 0 3.42 0l6.58-6.58a2.426 2.426 0 0 0 0-3.42z"/><circle cx="7.5" cy="7.5" r=".5" fill="currentColor"/>'],
        ['Wide Range of', 'Stock Lots', '<path d="M12.83 2.18a2 2 0 0 0-1.66 0L2.6 6.08a1 1 0 0 0 0 1.83l8.58 3.91a2 2 0 0 0 1.66 0l8.58-3.9a1 1 0 0 0 0-1.83Z"/><path d="m2 12 9.17 4.17a2 2 0 0 0 1.66 0L22 12"/><path d="m2 17 9.17 4.17a2 2 0 0 0 1.66 0L22 17"/>'],
        ['Fast & Reliable', 'Shipping', '<path d="M14 18V6a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v11a1 1 0 0 0 1 1h2"/><path d="M15 18H9"/><path d="M19 18h2a1 1 0 0 0 1-1v-3.65a1 1 0 0 0-.22-.624l-3.48-4.35A1 1 0 0 0 17.52 8H14"/><circle cx="17" cy="18" r="2"/><circle cx="7" cy="18" r="2"/>'],
    ];
@endphp
<section class="mt-2 md:mt-3 bg-baby text-night" aria-label="Why shop with us">
    <ul class="edge grid grid-cols-2 lg:grid-cols-4 gap-x-3 gap-y-4 md:gap-6 py-5 md:py-7">
        @foreach ($points as [$first, $second, $icon])
            <li class="flex items-center gap-2.5 md:gap-4 min-w-0">
                <span class="grid place-items-center shrink-0 w-10 h-10 md:w-14 md:h-14 bg-day/40">
                    <svg class="w-5 h-5 md:w-7 md:h-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $icon !!}</svg>
                </span>
                <span class="text-[13px] md:text-[17px] font-medium leading-snug">{{ $first }}<br>{{ $second }}</span>
            </li>
        @endforeach
    </ul>
</section>

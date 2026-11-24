{{-- Hanger drawn like the one in the logo (round hook, curved neck, open shoulders), between two thin lines.
     $align: 'center' (default) or 'start' for left-aligned headings. --}}
<div class="flex items-center {{ ($align ?? 'center') === 'start' ? 'justify-start' : 'justify-center' }} gap-4 text-ink mb-4" data-reveal="fade" aria-hidden="true">
    <span class="w-8 md:w-12 h-px bg-current"></span>
    <svg class="w-16 h-[35px] md:w-20 md:h-[44px]" viewBox="0 0 68 37" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
        <path d="M29.2 8.6A4.5 4.5 0 1 1 37 8.2C35.4 10.4 33.6 11 33.6 13.2V21.4"/>
        <path d="M2 36 30.4 22.5Q33.6 21 36.8 22.5L66 36"/>
    </svg>
    <span class="w-8 md:w-12 h-px bg-current"></span>
</div>

@props(['style' => 'icon'])
{{-- Light / dark switch. style: icon (header), segmented (footer, phone menu). --}}
@if ($style === 'segmented')
    <div x-data {{ $attributes->merge(['class' => 'inline-flex items-center border border-current/30 p-0.5 eyebrow text-[10px]']) }} role="group" aria-label="Colour theme">
        <button type="button" @click="$store.theme.set('light')" class="px-3 py-1.5 transition-colors duration-300"
                :class="!$store.theme.dark ? 'bg-current/15' : 'opacity-60 hover:opacity-100'" :aria-pressed="(!$store.theme.dark).toString()">Light</button>
        <button type="button" @click="$store.theme.set('dark')" class="px-3 py-1.5 transition-colors duration-300"
                :class="$store.theme.dark ? 'bg-current/15' : 'opacity-60 hover:opacity-100'" :aria-pressed="$store.theme.dark.toString()">Dark</button>
    </div>
@else
    <button type="button" x-data @click="$store.theme.toggle()" {{ $attributes->merge(['class' => 'relative w-[18px] h-[18px] grid place-items-center']) }}
            :aria-label="$store.theme.dark ? 'Switch to light theme' : 'Switch to dark theme'" title="Light / dark theme">
        {{-- Sun --}}
        <svg class="absolute inset-0 w-full h-full transition-all duration-500 ease-[var(--ease-out-quint)]"
             :class="$store.theme.dark ? 'opacity-100 rotate-0 scale-100' : 'opacity-0 -rotate-90 scale-50'"
             viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" aria-hidden="true">
            <circle cx="12" cy="12" r="4.2"/>
            <path d="M12 2.5v2M12 19.5v2M2.5 12h2M19.5 12h2M5.3 5.3l1.4 1.4M17.3 17.3l1.4 1.4M5.3 18.7l1.4-1.4M17.3 6.7l1.4-1.4"/>
        </svg>
        {{-- Moon --}}
        <svg class="absolute inset-0 w-full h-full transition-all duration-500 ease-[var(--ease-out-quint)]"
             :class="$store.theme.dark ? 'opacity-0 rotate-90 scale-50' : 'opacity-100 rotate-0 scale-100'"
             viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" aria-hidden="true">
            <path d="M20 14.5A8 8 0 0 1 9.5 4a8 8 0 1 0 10.5 10.5z"/>
        </svg>
    </button>
@endif

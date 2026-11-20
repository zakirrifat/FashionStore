<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>{{ trim($__env->yieldContent('title')) ? trim($__env->yieldContent('title')).' – ' : '' }}{{ config('app.name') }}</title>
    <meta name="description" content="@yield('description', $settings['seo_description'] ?? '')">
    <link rel="canonical" href="{{ url()->current() }}">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#faf9f6">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <meta property="og:site_name" content="{{ config('app.name') }}">
    <meta property="og:title" content="{{ trim($__env->yieldContent('title')) ?: config('app.name') }}">
    @stack('head')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
@php
    $overlay = $__env->hasSection('header-overlay');
    $minimal = $__env->hasSection('minimal-chrome');
@endphp
<body class="min-h-screen flex flex-col" data-wishlist-count="{{ (int) $wishlistCount }}" data-bag-count="{{ (int) $bagCount }}">
<a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:z-[100] focus:top-2 focus:left-2 focus:p-3 focus:bg-paper">Skip to main content</a>

{{-- ───────── Announcement bar ───────── --}}
@if (! $minimal && ! empty($settings['announcement_enabled']) && filled($settings['announcement_text']))
    <div class="relative z-50 bg-ink text-paper eyebrow text-[10px] text-center py-2.5 gutter">
        @if (filled($settings['announcement_link']))
            <a href="{{ $settings['announcement_link'] }}" class="link-draw">{{ $settings['announcement_text'] }}</a>
        @else
            {{ $settings['announcement_text'] }}
        @endif
    </div>
@endif

{{-- ───────── Header ───────── --}}
@if ($minimal)
    <header class="border-b border-line bg-paper">
        <div class="gutter h-16 grid grid-cols-[1fr_auto_1fr] items-center">
            <a href="{{ route('bag') }}" class="label link-draw justify-self-start">← Bag</a>
            <a href="{{ route('shop.home') }}" class="font-serif text-[22px] tracking-[-0.03em]">{{ config('app.name') }}</a>
            <span class="label text-mute justify-self-end hidden sm:inline">Secure checkout</span>
        </div>
    </header>
@else
<header data-header @if ($overlay) data-overlay @endif
        x-data="{ open: null, mobile: false, panel: null, timer: null }"
        @keydown.escape.window="open = null; mobile = false"
        :class="{ 'is-solid': open !== null || mobile }"
        class="group/header {{ $overlay ? 'fixed' : 'sticky' }} top-0 inset-x-0 z-50 transition-[transform,background-color,color,border-color] duration-500 ease-[var(--ease-out-quint)]
               {{ $overlay
                    ? 'text-paper border-b border-transparent [&.is-solid]:bg-paper [&.is-solid]:text-ink [&.is-solid]:border-line hover:bg-paper hover:text-ink hover:border-line'
                    : 'bg-paper/95 backdrop-blur-md text-ink border-b border-line' }}">
    <div class="gutter h-16 grid grid-cols-[1fr_auto_1fr] items-center">
        {{-- Left: main menu --}}
        <nav class="hidden lg:flex gap-7 label" aria-label="Main">
            @foreach ($menu as $top)
                <a href="{{ route('shop.category', $top) }}"
                   @mouseenter="clearTimeout(timer); timer = setTimeout(() => open = {{ $top->id }}, 90)"
                   @mouseleave="clearTimeout(timer)"
                   @focus="open = {{ $top->id }}"
                   class="link-draw py-5 {{ request()->is('c/'.$top->slug.'*') ? 'is-active' : '' }}">{{ $top->name }}</a>
            @endforeach
        </nav>
        <button class="lg:hidden label text-left flex items-center gap-2 justify-self-start" @click="mobile = !mobile; panel = null; document.documentElement.style.overflow = mobile ? 'hidden' : ''" :aria-expanded="mobile">
            <span class="relative w-5 h-3 inline-block" aria-hidden="true">
                <span class="absolute left-0 right-0 h-px bg-current top-0 transition-transform duration-300" :class="mobile && 'translate-y-[6px] rotate-45'"></span>
                <span class="absolute left-0 right-0 h-px bg-current bottom-0 transition-transform duration-300" :class="mobile && '-translate-y-[5px] -rotate-45'"></span>
            </span>
            <span class="hidden sm:inline" x-text="mobile ? 'Close' : 'Menu'">Menu</span>
        </button>

        {{-- Centre: wordmark --}}
        <a href="{{ route('shop.home') }}" class="font-serif text-[19px] sm:text-[22px] md:text-[26px] tracking-[-0.035em] leading-none whitespace-nowrap px-3" aria-label="{{ config('app.name') }} — home">{{ config('app.name') }}</a>

        {{-- Right: tools --}}
        <div class="flex justify-end items-center gap-3 sm:gap-5 label" x-data="search" @keydown.escape.window="open = false">
            <button @click="toggle()" class="link-draw" :aria-expanded="open" aria-label="Search">
                <svg class="w-[18px] h-[18px] sm:hidden" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" aria-hidden="true"><circle cx="10.5" cy="10.5" r="6.5"/><path d="M15.5 15.5L21 21"/></svg>
                <span class="hidden sm:inline">Search</span>
            </button>
            <a href="{{ auth()->check() ? route('account.overview') : route('login') }}" class="link-draw hidden md:inline">{{ auth()->check() ? 'Account' : 'Sign in' }}</a>
            <a href="{{ route('wishlist') }}" class="link-draw hidden md:inline">Wishlist <sup x-text="$store.wishlist.count || ''">{{ $wishlistCount ?: '' }}</sup></a>
            <a href="{{ route('bag') }}" class="link-draw" @click.prevent="$store.bag.show()">Bag <sup x-text="$store.bag.count">{{ (int) $bagCount }}</sup></a>

            {{-- Search overlay --}}
            <template x-teleport="body">
                <div x-show="open" x-cloak class="fixed inset-0 z-[80]" role="dialog" aria-modal="true" aria-label="Search">
                    <div class="scrim" @click="open = false" x-show="open" x-transition.opacity.duration.400ms></div>
                    <div x-show="open"
                         x-transition:enter="transition duration-500 ease-[var(--ease-out-quint)]" x-transition:enter-start="-translate-y-full"
                         x-transition:leave="transition duration-300" x-transition:leave-end="-translate-y-full"
                         class="relative z-[81] bg-paper text-ink max-h-[90vh] overflow-y-auto">
                        <form action="{{ route('shop.search') }}" method="get" class="gutter pt-8 pb-6 flex items-end gap-6 border-b border-line">
                            <label class="flex-1">
                                <span class="eyebrow text-mute">Search</span>
                                <input x-ref="input" x-model="q" @input="type()" name="q" autocomplete="off" placeholder="What are you looking for?"
                                       class="w-full bg-transparent outline-none font-serif text-[32px] md:text-[52px] tracking-[-0.03em] leading-tight pt-2 placeholder:text-[#c9c5bb]">
                            </label>
                            <button type="button" class="label link-draw pb-3" @click="open = false">Close</button>
                        </form>
                        <div class="gutter py-8 min-h-[160px]">
                            <p x-show="q.trim().length < 2" class="text-mute">Type at least two letters.</p>
                            <p x-show="q.trim().length >= 2 && !loading && results.length === 0" x-cloak class="text-mute">No products found for “<span x-text="q"></span>”.</p>
                            <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-6 gap-2">
                                <template x-for="(r, i) in results" :key="r.url">
                                    <a :href="r.url" class="group block" :style="`animation: vt-in 600ms var(--ease-out-quint) ${i * 50}ms both`">
                                        <div class="img-frame aspect-[3/4]">
                                            <img x-show="r.image" :src="r.image" alt="" class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-[1.03]">
                                        </div>
                                        <p class="label mt-2 truncate" x-text="r.name"></p>
                                        <p class="text-mute text-[12px]" x-text="r.price"></p>
                                    </a>
                                </template>
                            </div>
                            <a x-show="results.length" :href="'{{ route('shop.search') }}?q=' + encodeURIComponent(q)" class="label link-u mt-8 inline-block">See all results</a>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </div>

    {{-- Mega menu (desktop) --}}
    @foreach ($menu as $top)
        @php
            $leafOnly = $top->children->every(fn ($c) => $c->children->isEmpty());
            $feature = $top->image ?: $top->children->pluck('image')->filter()->first();
        @endphp
        <div x-cloak x-show="open === {{ $top->id }}" @mouseleave="open = null"
             x-transition:enter="transition duration-500 ease-[var(--ease-out-quint)]" x-transition:enter-start="opacity-0 -translate-y-2"
             x-transition:leave="transition duration-200" x-transition:leave-end="opacity-0"
             class="hidden lg:block absolute inset-x-0 top-full bg-paper text-ink border-b border-line">
            <div class="gutter py-10 grid grid-cols-12 gap-8">
                <div class="col-span-8 flex gap-16" x-data x-init="$watch('open', v => { if (v === {{ $top->id }}) [...$el.querySelectorAll('li')].forEach((li, i) => li.animate([{opacity: 0, transform: 'translateY(8px)'}, {opacity: 1, transform: 'none'}], {duration: 500, delay: i * 25, easing: 'cubic-bezier(0.22,1,0.36,1)', fill: 'both'})) })">
                    @if ($leafOnly)
                        <ul class="space-y-2.5">
                            <li><a class="link-draw font-medium" href="{{ route('shop.category', $top) }}">View all</a></li>
                            @foreach ($top->children as $child)
                                <li><a class="link-draw" href="{{ route('shop.category', $child) }}">{{ $child->name }}</a></li>
                            @endforeach
                        </ul>
                    @else
                        @foreach ($top->children as $group)
                            <div>
                                <p class="eyebrow text-mute mb-4">{{ $group->name }}</p>
                                <ul class="space-y-2.5">
                                    <li><a class="link-draw" href="{{ route('shop.category', $group) }}">View all</a></li>
                                    @foreach ($group->children as $child)
                                        <li><a class="link-draw" href="{{ route('shop.category', $child) }}">{{ $child->name }}</a></li>
                                    @endforeach
                                </ul>
                            </div>
                        @endforeach
                    @endif
                </div>
                <a href="{{ route('shop.category', $top) }}" class="col-span-4 group block">
                    <div class="img-frame aspect-[4/3]">
                        @if ($feature)
                            <img src="{{ \App\Support\Img::url($feature, 800) }}" alt="" loading="lazy" class="w-full h-full object-cover transition-transform duration-[1400ms] ease-[var(--ease-out-quint)] group-hover:scale-[1.04]">
                        @else
                            <div class="absolute inset-0 grid place-items-center font-serif text-[40px] text-[#cfcbc1]">{{ $top->name }}</div>
                        @endif
                    </div>
                    <p class="label mt-3"><span class="link-draw">Shop {{ $top->name }}</span> →</p>
                </a>
            </div>
        </div>
    @endforeach

    {{-- Mobile menu: sliding panels --}}
    <div x-cloak x-show="mobile" class="lg:hidden fixed inset-x-0 top-16 bottom-0 bg-paper text-ink overflow-hidden"
         x-transition:enter="transition duration-500 ease-[var(--ease-out-quint)]" x-transition:enter-start="opacity-0"
         x-transition:leave="transition duration-200" x-transition:leave-end="opacity-0">
        <div class="relative h-full">
            <div class="absolute inset-0 overflow-y-auto gutter py-8 transition-transform duration-500 ease-[var(--ease-out-quint)]" :class="panel !== null && '-translate-x-full'">
                <ul class="space-y-1">
                    @foreach ($menu as $top)
                        <li><button class="w-full flex justify-between items-center py-2 font-serif text-[34px] tracking-[-0.03em]" @click="panel = {{ $top->id }}">{{ $top->name }} <span class="text-[18px]">→</span></button></li>
                    @endforeach
                </ul>
                <div class="mt-10 pt-6 border-t border-line grid grid-cols-2 gap-3 label">
                    <a href="{{ auth()->check() ? route('account.overview') : route('login') }}">{{ auth()->check() ? 'My account' : 'Sign in' }}</a>
                    <a href="{{ route('wishlist') }}">Wishlist ({{ (int) $wishlistCount }})</a>
                    <a href="{{ route('shop.contact') }}">Contact</a>
                    <a href="{{ route('shop.faq') }}">Help</a>
                </div>
            </div>
            @foreach ($menu as $top)
                <div class="absolute inset-0 overflow-y-auto gutter py-8 bg-paper transition-transform duration-500 ease-[var(--ease-out-quint)]"
                     :class="panel === {{ $top->id }} ? 'translate-x-0' : 'translate-x-full'">
                    <button class="label mb-6" @click="panel = null">← Back</button>
                    <a href="{{ route('shop.category', $top) }}" class="block font-serif text-[34px] tracking-[-0.03em] mb-6">All {{ $top->name }}</a>
                    @foreach ($top->children as $group)
                        <div class="border-t border-line py-4">
                            <a href="{{ route('shop.category', $group) }}" class="label">{{ $group->name }}</a>
                            @if ($group->children->isNotEmpty())
                                <ul class="mt-3 grid grid-cols-2 gap-y-2 text-[15px]">
                                    @foreach ($group->children as $child)
                                        <li><a href="{{ route('shop.category', $child) }}">{{ $child->name }}</a></li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endforeach
        </div>
    </div>
</header>
@endif

<main id="main" class="flex-1">
    @yield('content')
</main>

{{-- ───────── Footer ───────── --}}
@unless ($minimal)
@php
    $socials = collect([
        'Instagram' => $settings['social_instagram'] ?? null, 'Facebook' => $settings['social_facebook'] ?? null,
        'TikTok' => $settings['social_tiktok'] ?? null, 'X' => $settings['social_x'] ?? null,
        'YouTube' => $settings['social_youtube'] ?? null, 'Pinterest' => $settings['social_pinterest'] ?? null,
    ])->filter();
@endphp
<footer class="mt-28 bg-ink text-paper overflow-hidden">
    <div class="gutter pt-20 pb-10 grid grid-cols-2 md:grid-cols-12 gap-x-6 gap-y-12">
        <div class="col-span-2 md:col-span-5" data-reveal>
            <p class="h2 max-w-md">News on collections, events and more.</p>
            <form onsubmit="event.preventDefault(); Alpine.store('toast').show('Newsletter sign-up opens soon — thank you for your interest.')" class="mt-8 flex items-end gap-4 max-w-md border-b border-paper/40 focus-within:border-paper transition-colors">
                <label class="flex-1">
                    <span class="sr-only">Email address</span>
                    <input type="email" required placeholder="Email address" class="w-full bg-transparent py-3 outline-none placeholder:text-paper/50">
                </label>
                <button class="label pb-3 link-draw">Sign up →</button>
            </form>
        </div>
        <div class="md:col-span-2 md:col-start-7" data-reveal style="--d: 80ms">
            <p class="eyebrow text-paper/50 mb-5">Client services</p>
            <ul class="space-y-2.5">
                <li><a href="{{ route('shop.contact') }}" class="link-draw">Contact us</a></li>
                <li><a href="{{ route('shop.faq') }}" class="link-draw">FAQs</a></li>
                @foreach ($footerPages->get('client_services', []) as $p)
                    <li><a href="{{ route('shop.page', $p->slug) }}" class="link-draw">{{ $p->title }}</a></li>
                @endforeach
            </ul>
        </div>
        <div class="md:col-span-2" data-reveal style="--d: 160ms">
            <p class="eyebrow text-paper/50 mb-5">Company</p>
            <ul class="space-y-2.5">
                @foreach ($footerPages->get('company', collect())->concat($footerPages->get('legal', [])) as $p)
                    <li><a href="{{ route('shop.page', $p->slug) }}" class="link-draw">{{ $p->title }}</a></li>
                @endforeach
            </ul>
        </div>
        <div class="md:col-span-2" data-reveal style="--d: 240ms">
            @if ($socials->isNotEmpty())
                <p class="eyebrow text-paper/50 mb-5">Follow</p>
                <ul class="space-y-2.5">
                    @foreach ($socials as $name => $link)
                        <li><a href="{{ $link }}" target="_blank" rel="noopener" class="link-draw">{{ $name }}</a></li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
    {{-- Oversized wordmark --}}
    <div class="gutter select-none" aria-hidden="true">
        <p class="font-serif leading-[0.8] tracking-[-0.06em] text-[18.5vw] whitespace-nowrap translate-y-[12%]" data-reveal>{{ config('app.name') }}</p>
    </div>
    <div class="gutter py-5 border-t border-paper/15 flex flex-wrap justify-between gap-2 eyebrow text-[10px] text-paper/60 relative bg-ink">
        <span>© {{ date('Y') }} {{ config('app.name') }}</span>
        <span>{{ $settings['country_label'] ?? '' }}</span>
    </div>
</footer>
@endunless

{{-- ───────── Slide-out bag (every page) ───────── --}}
<div x-data x-show="$store.bag.open" x-cloak class="fixed inset-0 z-[90]" role="dialog" aria-modal="true" aria-label="Bag"
     @keydown.escape.window="$store.bag.close()"
     x-effect="document.documentElement.style.overflow = $store.bag.open ? 'hidden' : ''">
    <div class="scrim" @click="$store.bag.close()" x-show="$store.bag.open" x-transition.opacity.duration.400ms></div>
    <aside class="drawer" x-show="$store.bag.open"
           x-transition:enter="transition duration-500 ease-[var(--ease-out-quint)]" x-transition:enter-start="translate-x-full"
           x-transition:leave="transition duration-300 ease-in" x-transition:leave-end="translate-x-full">
        <div class="drawer-head">
            <span class="label">Bag <span x-text="'(' + $store.bag.count + ')'"></span></span>
            <button class="label link-draw" @click="$store.bag.close()">Close</button>
        </div>
        <div class="relative flex-1 min-h-0 flex flex-col" :class="$store.bag.loading && 'opacity-60 pointer-events-none'" x-html="$store.bag.html"
             @submit.prevent="$store.bag.submit($event.target)"
             @change="if ($event.target.matches('[data-qty]')) $store.bag.submit($event.target.form)">
            <div class="p-6 space-y-4">
                <div class="skeleton h-28"></div><div class="skeleton h-28"></div>
            </div>
        </div>
    </aside>
</div>

{{-- ───────── Toasts ───────── --}}
<div x-data class="fixed z-[100] bottom-5 left-1/2 -translate-x-1/2 flex flex-col items-center gap-2 pointer-events-none" aria-live="polite">
    <template x-for="t in $store.toast.items" :key="t.id">
        <div x-transition:enter="transition duration-500 ease-[var(--ease-out-quint)]" x-transition:enter-start="opacity-0 translate-y-4"
             class="pointer-events-auto bg-ink text-paper px-5 py-3 text-[13px] shadow-xl max-w-[90vw]"
             :class="t.tone === 'warn' && 'bg-sale'" x-text="t.message"></div>
    </template>
</div>

@if (session('status') && ! app()->bound('inline-status-shown'))
    <script>document.addEventListener('alpine:init', () => queueMicrotask(() => Alpine.store('toast').show(@js(session('status')))));</script>
@endif
</body>
</html>

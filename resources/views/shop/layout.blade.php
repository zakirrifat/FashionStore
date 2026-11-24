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
    {{-- Apply the saved (or device) theme before anything is drawn, so there is no flash --}}
    <script>(function(){var t=null;try{t=localStorage.getItem('theme')}catch(e){}if(t!=='light'&&t!=='dark'){t=window.matchMedia('(prefers-color-scheme: dark)').matches?'dark':'light'}document.documentElement.dataset.theme=t;if(t==='dark'){document.querySelector('meta[name=theme-color]').setAttribute('content','#121110')}})();</script>
    @if ($icon32 = \App\Support\Brand::url('icon_32'))
        <link rel="icon" href="{{ $icon32 }}" type="image/png" sizes="32x32">
        <link rel="icon" href="{{ \App\Support\Brand::url('icon_512') }}" type="image/png" sizes="512x512">
        <link rel="apple-touch-icon" href="{{ \App\Support\Brand::url('icon_180') }}">
    @else
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    @endif
    @if (! $__env->yieldPushContent('head') || ! str_contains($__env->yieldPushContent('head'), 'og:image'))
        @if ($og = \App\Support\Brand::url('og'))<meta property="og:image" content="{{ $og }}">@endif
    @endif
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
            <a href="{{ route('shop.home') }}" aria-label="{{ config('app.name') }} — home"><x-shop.logo class="h-9 md:h-10" text-class="text-[16px] sm:text-[20px]" /></a>
            <span class="label text-mute justify-self-end hidden sm:inline">Secure checkout</span>
        </div>
    </header>
@else
{{-- Invisible strip at the top of the window: moving the mouse here brings the hidden header back --}}
<div data-header-zone class="hidden fixed top-0 inset-x-0 h-6 z-[49]" aria-hidden="true"></div>
<header data-header @if ($overlay) data-overlay @endif
        x-data="{ open: null, mobile: false, panel: null, timer: null }"
        @keydown.escape.window="open = null; mobile = false"
        :class="{ 'is-solid': open !== null || mobile, 'is-open': open !== null || mobile }"
        class="group/header {{ $overlay ? 'fixed' : 'sticky' }} top-0 inset-x-0 z-50 transition-[transform,background-color,color,border-color] duration-500 ease-[var(--ease-out-quint)]
               {{ $overlay
                    ? 'text-day border-b border-transparent [&.is-solid]:bg-paper [&.is-solid]:text-ink [&.is-solid]:border-line hover:bg-paper hover:text-ink hover:border-line'
                    : 'bg-paper text-ink border-b border-line' }}">
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
        <a href="{{ route('shop.home') }}" class="leading-none px-3" aria-label="{{ config('app.name') }} — home">
            <x-shop.logo class="h-9 md:h-11" text-class="text-[15px] sm:text-[20px] md:text-[24px]" />
        </a>

        {{-- Right: tools --}}
        <div class="flex justify-end items-center gap-3 sm:gap-5 label" x-data="search" @keydown.escape.window="close()">
            <button x-ref="trigger" @click="toggle()" class="link-draw" :aria-expanded="open" aria-label="Search">
                <svg class="w-[18px] h-[18px] sm:hidden" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" aria-hidden="true"><circle cx="10.5" cy="10.5" r="6.5"/><path d="M15.5 15.5L21 21"/></svg>
                <span class="hidden sm:inline">Search</span>
            </button>
            <x-shop.theme-toggle />

            <a href="{{ auth()->check() ? route('account.overview') : route('login') }}" class="link-draw hidden md:inline">{{ auth()->check() ? 'Account' : 'Sign in' }}</a>
            @if (config('shop.checkout_enabled'))
                <a href="{{ route('wishlist') }}" class="link-draw hidden md:inline">Wishlist <sup x-text="$store.wishlist.count || ''">{{ $wishlistCount ?: '' }}</sup></a>
                <a href="{{ route('bag') }}" class="link-draw" @click.prevent="$store.bag.show()">Bag <sup x-text="$store.bag.count">{{ (int) $bagCount }}</sup></a>
            @else
                {{-- Catalogue mode: no bag, so the wishlist takes its place on phones too --}}
                <a href="{{ route('wishlist') }}" class="link-draw" aria-label="Wishlist">
                    <svg class="w-[18px] h-[18px] sm:hidden inline" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" aria-hidden="true"><path d="M12 20.5s-7.5-4.6-9.3-9.2C1.4 8 3.5 4.5 7 4.5c2.1 0 3.6 1.2 5 3 1.4-1.8 2.9-3 5-3 3.5 0 5.6 3.5 4.3 6.8-1.8 4.6-9.3 9.2-9.3 9.2z"/></svg><span class="hidden sm:inline">Wishlist</span> <sup x-text="$store.wishlist.count || ''">{{ $wishlistCount ?: '' }}</sup>
                </a>
            @endif

            @include('shop.partials.search-overlay')
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
            @php
                // Leaf-only menus show one group headed by the top category; otherwise one group per child.
                $groups = $leafOnly ? collect([$top]) : $top->children;
                $groupCols = [1 => 'grid-cols-1', 2 => 'grid-cols-2'][$groups->count()] ?? 'grid-cols-3';
            @endphp
            <div class="gutter py-10 grid grid-cols-12 gap-10">
                <div class="col-span-8 grid {{ $groupCols }} gap-x-10 gap-y-8 content-start" x-data x-init="$watch('open', v => { if (v === {{ $top->id }}) [...$el.querySelectorAll('li')].forEach((li, i) => li.animate([{opacity: 0, transform: 'translateY(8px)'}, {opacity: 1, transform: 'none'}], {duration: 500, delay: i * 25, easing: 'cubic-bezier(0.22,1,0.36,1)', fill: 'both'})) })">
                    @foreach ($groups as $group)
                        <div class="min-w-0">
                            <p class="menu-heading">{{ $group->name }}</p>
                            {{-- A single long group splits its rows into two columns so the menu stays short --}}
                            <ul class="grid {{ $groups->count() === 1 && $group->children->count() > 6 ? 'grid-cols-2 gap-x-8' : 'grid-cols-1' }}">
                                <li class="col-span-full">
                                    <a class="menu-row menu-row-all" href="{{ route('shop.category', $group) }}">
                                        <x-shop.category-icon icon="hanger" />View all<span class="menu-chev" aria-hidden="true">→</span>
                                    </a>
                                </li>
                                @foreach ($group->children as $child)
                                    <li>
                                        <a class="menu-row" href="{{ route('shop.category', $child) }}">
                                            <x-shop.category-icon :name="$child->name" /><span class="truncate">{{ $child->name }}</span><span class="menu-chev" aria-hidden="true">›</span>
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endforeach
                </div>
                <a href="{{ route('shop.category', $top) }}" class="col-span-4 group block">
                    <div class="img-frame aspect-[4/3]">
                        @if ($feature)
                            <img src="{{ \App\Support\Img::url($feature, 800) }}" alt="" loading="lazy" class="w-full h-full object-cover transition-transform duration-[1400ms] ease-[var(--ease-out-quint)] group-hover:scale-[1.04]">
                        @else
                            <div class="absolute inset-0 grid place-items-center font-serif text-[40px] text-mute/40">{{ $top->name }}</div>
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
                <div class="mt-8 flex items-center justify-between">
                    <span class="eyebrow text-[10px] text-mute">Theme</span>
                    <x-shop.theme-toggle style="segmented" />
                </div>
            </div>
            @foreach ($menu as $top)
                <div class="absolute inset-0 overflow-y-auto gutter py-8 bg-paper transition-transform duration-500 ease-[var(--ease-out-quint)]"
                     :class="panel === {{ $top->id }} ? 'translate-x-0' : 'translate-x-full'">
                    <button class="label mb-6" @click="panel = null">← Back</button>
                    <a href="{{ route('shop.category', $top) }}" class="block font-serif text-[34px] tracking-[-0.03em] mb-6">All {{ $top->name }}</a>
                    @php
                        $leafOnly = $top->children->every(fn ($c) => $c->children->isEmpty());
                        $groups = $leafOnly ? collect([$top]) : $top->children;
                    @endphp
                    @foreach ($groups as $group)
                        <div class="mt-8 first-of-type:mt-0">
                            <p class="menu-heading">{{ $leafOnly ? 'Categories' : $group->name }}</p>
                            <ul>
                                {{-- The big "All …" link above already covers a leaf-only menu --}}
                                @unless ($leafOnly)
                                    <li>
                                        <a href="{{ route('shop.category', $group) }}" class="menu-row menu-row-all h-14 text-[15px]">
                                            <x-shop.category-icon icon="hanger" />View all<span class="menu-chev" aria-hidden="true">→</span>
                                        </a>
                                    </li>
                                @endunless
                                @foreach ($group->children as $child)
                                    <li>
                                        <a href="{{ route('shop.category', $child) }}" class="menu-row h-14 text-[15px]">
                                            <x-shop.category-icon :name="$child->name" /><span class="truncate">{{ $child->name }}</span><span class="menu-chev" aria-hidden="true">›</span>
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
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
<footer class="section-mt bg-paper-2 text-ink border-t border-line overflow-hidden">
    <div class="gutter pt-14 md:pt-16 pb-3 md:pb-4 grid grid-cols-2 md:grid-cols-12 gap-x-6 lg:gap-x-10 gap-y-12">
        <div class="col-span-2 md:col-span-12 lg:col-span-4" data-reveal>
            <p class="h2 max-w-md">News on collections, events and more.</p>
            <form onsubmit="event.preventDefault(); Alpine.store('toast').show('Newsletter sign-up opens soon — thank you for your interest.')" class="mt-8 flex items-end gap-4 max-w-md border-b border-line-strong focus-within:border-ink transition-colors">
                <label class="flex-1">
                    <span class="sr-only">Email address</span>
                    <input type="email" required placeholder="Email address" class="w-full bg-transparent py-3 outline-none placeholder:text-mute">
                </label>
                <button class="label pb-3 link-draw">Sign up →</button>
            </form>
        </div>
        <nav class="md:col-span-4 lg:col-span-2 lg:col-start-6" aria-label="Client services" data-reveal style="--d: 80ms">
            <p class="menu-heading mb-2">Client services</p>
            <ul>
                <li><a href="{{ route('shop.contact') }}" class="foot-link"><span>Contact us</span></a></li>
                <li><a href="{{ route('shop.faq') }}" class="foot-link"><span>FAQs</span></a></li>
                @foreach ($footerPages->get('client_services', []) as $p)
                    <li><a href="{{ route('shop.page', $p->slug) }}" class="foot-link"><span>{{ $p->title }}</span></a></li>
                @endforeach
            </ul>
        </nav>
        <nav class="md:col-span-4 lg:col-span-2" aria-label="Company" data-reveal style="--d: 160ms">
            <p class="menu-heading mb-2">Company</p>
            <ul>
                @foreach ($footerPages->get('company', collect())->concat($footerPages->get('legal', [])) as $p)
                    <li><a href="{{ route('shop.page', $p->slug) }}" class="foot-link"><span>{{ $p->title }}</span></a></li>
                @endforeach
            </ul>
        </nav>
        {{-- Get in touch: contact details from Admin › Settings (each shown only when filled in) + social links --}}
        <div class="col-span-2 md:col-span-4 lg:col-span-3" data-reveal style="--d: 240ms">
            <p class="menu-heading mb-2">Get in touch</p>
            <ul>
                @if (filled($settings['contact_email'] ?? null))
                    <li><a href="mailto:{{ $settings['contact_email'] }}" class="foot-link"><span class="truncate">{{ $settings['contact_email'] }}</span></a></li>
                @endif
                @if (filled($settings['contact_phone'] ?? null))
                    <li><a href="tel:{{ preg_replace('/[^0-9+]/', '', $settings['contact_phone']) }}" class="foot-link"><span>{{ $settings['contact_phone'] }}</span></a></li>
                @endif
                @if (blank($settings['contact_email'] ?? null) && blank($settings['contact_phone'] ?? null))
                    <li><a href="{{ route('shop.contact') }}" class="foot-link"><span>Send us a message</span></a></li>
                @endif
            </ul>
            @if (filled($settings['contact_hours'] ?? null) || filled($settings['contact_address'] ?? null))
                <div class="mt-4 space-y-1.5 text-[13px] text-mute leading-relaxed">
                    @if (filled($settings['contact_hours'] ?? null))<p>{{ $settings['contact_hours'] }}</p>@endif
                    @if (filled($settings['contact_address'] ?? null))<p class="whitespace-pre-line">{{ $settings['contact_address'] }}</p>@endif
                </div>
            @endif
            @if ($socials->isNotEmpty())
                <ul class="mt-6 flex flex-wrap gap-x-5 gap-y-2 label" aria-label="Follow us">
                    @foreach ($socials as $name => $link)
                        <li><a href="{{ $link }}" target="_blank" rel="noopener" class="link-draw">{{ $name }}</a></li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
    {{-- Oversized wordmark --}}
    <div class="gutter select-none" aria-hidden="true">
        @if (\App\Support\Brand::hasLogo())
            <div class="pb-2 md:pb-3 flex justify-center" data-reveal><x-shop.logo class="h-[18vw] max-h-[150px] md:h-[10vw] mx-auto" /></div>
        @else
            <p class="font-serif leading-[0.8] tracking-[-0.04em] text-[9.4vw] whitespace-nowrap translate-y-[12%]" data-reveal>{{ config('app.name') }}</p>
        @endif
    </div>
    <div class="gutter py-5 border-t border-line flex flex-wrap justify-between items-center gap-4 eyebrow text-[10px] text-mute relative bg-paper-2">
        <span>© {{ date('Y') }} {{ config('app.name') }}</span>
        <button type="button" class="link-draw text-ink inline-flex items-center gap-1.5"
                onclick="window.lenis ? window.lenis.scrollTo(0) : window.scrollTo({ top: 0, behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' })">
            Back to top <span aria-hidden="true">↑</span>
        </button>
    </div>
</footer>
@endunless

{{-- ───────── Slide-out bag (every page, when checkout is on) ───────── --}}
@if (config('shop.checkout_enabled'))
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
@endif

@include('shop.partials.enquiry-modal')

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

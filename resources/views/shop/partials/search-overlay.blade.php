{{-- ───────── Search panel. Lives inside x-data="search" in the header ───────── --}}
<template x-teleport="body">
    <div x-show="open" x-cloak class="fixed inset-0 z-[80]" role="dialog" aria-modal="true" aria-label="Search">
        <div class="scrim" @click="close()" x-show="open" x-transition.opacity.duration.400ms></div>
        <div x-show="open" x-ref="panel" @keydown="nav($event)"
             x-transition:enter="transition duration-500 ease-[var(--ease-out-quint)]" x-transition:enter-start="-translate-y-full"
             x-transition:leave="transition duration-300" x-transition:leave-end="-translate-y-full"
             class="relative z-[81] bg-paper text-ink h-[100dvh] sm:h-auto sm:max-h-[90vh] overflow-y-auto normal-case tracking-normal text-[14px] font-normal">

            {{-- Logo, search field, close --}}
            <div class="sticky top-0 z-10 bg-paper border-b border-line">
                <div class="gutter py-3 sm:py-4 grid grid-cols-[1fr_auto] sm:grid-cols-[auto_minmax(0,1fr)_auto] items-center gap-x-6 lg:gap-x-10 gap-y-3">
                    <a href="{{ route('shop.home') }}" class="justify-self-start leading-none" aria-label="{{ config('app.name') }} — home">
                        <x-shop.logo class="h-9 md:h-10" text-class="text-[18px] md:text-[22px]" />
                    </a>

                    <form action="{{ route('shop.search') }}" method="get" role="search" @submit="submit($event)"
                          class="col-span-2 sm:col-span-1 order-last sm:order-none w-full max-w-2xl sm:mx-auto">
                        <div class="flex items-center h-11 px-3.5 gap-2.5 border border-line-strong focus-within:border-ink transition-colors">
                            <svg class="w-[18px] h-[18px] shrink-0 text-mute" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><circle cx="10.5" cy="10.5" r="6.5"/><path d="M15.5 15.5L21 21"/></svg>
                            <input x-ref="input" x-model="q" @input="type()" name="q" type="text" autocomplete="off" enterkeyhint="search"
                                   aria-label="Search products" placeholder="Search products, colours, styles…"
                                   class="flex-1 min-w-0 h-full bg-transparent outline-none focus-visible:outline-none text-[16px] sm:text-[15px] placeholder:text-mute">
                            <span x-show="loading" x-cloak class="w-4 h-4 shrink-0 rounded-full border border-line-strong border-t-ink animate-spin" aria-hidden="true"></span>
                            <button type="button" x-show="q.length && !loading" x-cloak @click="clearQuery()"
                                    class="w-6 h-6 shrink-0 grid place-items-center text-mute hover:text-ink transition-colors" aria-label="Clear search">
                                <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M5 5l14 14M19 5L5 19"/></svg>
                            </button>
                        </div>
                    </form>

                    <button type="button" class="label link-draw justify-self-end" @click="close()">Close</button>
                </div>
            </div>

            <div class="gutter py-6 md:py-8" aria-live="polite">
                <p x-show="!idle && !hasMatches && !noResults && !failed" x-cloak class="text-mute">Searching…</p>
                <p x-show="failed" x-cloak class="text-mute">Search isn’t responding right now. Press Enter to try the full search page.</p>

                {{-- While typing: matching categories and products --}}
                <div x-show="hasMatches" x-cloak>
                    <div x-show="categories.length" class="mb-6">
                        <p class="eyebrow text-mute mb-3">Categories</p>
                        <div class="flex flex-wrap gap-2">
                            <template x-for="c in categories" :key="c.url">
                                <a :href="c.url" data-search-option @click="remember()" x-text="c.name"
                                   class="px-3 py-1.5 border border-line text-[13px] hover:border-ink transition-colors"></a>
                            </template>
                        </div>
                    </div>

                    <div x-show="results.length">
                        <p class="eyebrow text-mute mb-3">Products</p>
                        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-x-6 gap-y-1">
                            <template x-for="r in results" :key="r.url">
                                <a :href="r.url" data-search-option @click="remember()" class="group flex items-center gap-3 p-1.5 -mx-1.5 hover:bg-paper-2 transition-colors">
                                    <span class="img-frame block w-12 h-16 shrink-0">
                                        <img x-show="r.image" :src="r.image" alt="" class="w-full h-full object-cover">
                                    </span>
                                    <span class="min-w-0">
                                        <span class="block truncate" x-text="r.name"></span>
                                        <span class="block text-mute text-[12px] truncate" x-text="r.colour"></span>
                                    </span>
                                </a>
                            </template>
                        </div>
                        <a :href="'{{ route('shop.search') }}?q=' + encodeURIComponent(term)" data-search-option @click="remember()"
                           class="label link-u mt-6 inline-block">See all results</a>
                    </div>
                </div>

                {{-- No matches --}}
                <p x-show="noResults" x-cloak class="mb-6">
                    No results for “<span x-text="term"></span>”. <span class="text-mute">Try a category or one of these.</span>
                </p>

                {{-- Before typing, and when nothing matched --}}
                <div x-show="showDiscover" class="flex flex-col md:flex-row gap-7 md:gap-12 lg:gap-16">
                    <div x-show="idle && recent.length" x-cloak class="md:w-44 shrink-0">
                        <div class="flex items-center justify-between mb-3">
                            <p class="eyebrow text-mute">Recent</p>
                            <button type="button" class="text-[12px] text-mute hover:text-ink transition-colors" @click="clearRecent()">Clear</button>
                        </div>
                        <ul class="space-y-1">
                            <template x-for="s in recent" :key="s">
                                <li><a :href="'{{ route('shop.search') }}?q=' + encodeURIComponent(s)" data-search-option @click="remember(s)"
                                       class="block py-1 truncate hover:text-mute transition-colors" x-text="s"></a></li>
                            </template>
                        </ul>
                    </div>

                    @if ($menu->isNotEmpty())
                        <div class="md:w-44 shrink-0">
                            <p class="eyebrow text-mute mb-3">Shop by category</p>
                            <ul class="space-y-1">
                                @foreach ($menu as $top)
                                    <li><a href="{{ route('shop.category', $top) }}" data-search-option
                                           class="flex items-center justify-between py-1 hover:text-mute transition-colors">{{ $top->name }}<span aria-hidden="true">→</span></a></li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <div x-show="featured.length" x-cloak class="flex-1 min-w-0 max-w-3xl">
                        <p class="eyebrow text-mute mb-3" x-text="featuredLabel"></p>
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                            <template x-for="f in featured" :key="f.url">
                                <div class="min-w-0">
                                    <a :href="f.url" data-search-option class="group block">
                                        <span class="img-frame block aspect-[3/4]">
                                            <img x-show="f.image" :src="f.image" alt="" loading="lazy" class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-[1.03]">
                                        </span>
                                        <span class="block mt-2 text-[13px] truncate" x-text="f.name"></span>
                                    </a>
                                    <button type="button" class="text-[12px] link-u mt-1" @click="$store.enquiry.open(f.enquiry)">Ask for price</button>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

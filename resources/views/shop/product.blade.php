@extends('shop.layout')
@php
    use App\Support\Img;
    use App\Http\Controllers\ShopController;

    $variants = $colour->variants->sortBy(fn ($v) => ShopController::sizeRank($v->size))->values();
    $sizes = $variants->pluck('size')->unique()->values();
    $lengths = $variants->pluck('length')->filter()->unique()->values();
    $stockMap = $variants->map(fn ($v) => [
        'id' => $v->id, 'size' => $v->size, 'length' => $v->length, 'stock' => $v->stock,
    ]);
    $details = collect(preg_split('/\r\n|\r|\n/', (string) $product->details))->map(fn ($l) => trim($l, " \t-•"))->filter();
    $images = $colour->images;
    $inStock = $variants->sum('stock') > 0;
    $saved = \App\Support\Wishlist::has($product, $colour);
    $enquiry = [
        'id' => $product->id, 'name' => $product->name, 'image' => $images->first()?->url(400),
        'colourId' => $colour->id, 'colour' => $colour->name,
    ];
@endphp

@section('title', $product->seo_title ?: $product->name.' – '.$colour->name)
@section('description', \Illuminate\Support\Str::limit($product->seo_description ?: strip_tags((string) $product->description) ?: $product->name, 155))

@push('head')
    @if ($images->first())
        <meta property="og:image" content="{{ $images->first()->url(1200) }}">
    @endif
    <script type="application/ld+json">{!! json_encode([
        '@context' => 'https://schema.org/',
        '@type' => 'Product',
        'name' => $product->name,
        'sku' => $product->style_code.'-'.$colour->code,
        'description' => strip_tags((string) $product->description),
        'image' => $images->map(fn ($i) => $i->url(1200))->values(),
        'color' => $colour->name,
        'brand' => ['@type' => 'Brand', 'name' => config('app.name')],
        'url' => $product->url($colour),
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>{{-- HEX_* flags: text like "</script>" can't end the block early --}}
@endpush

@section('content')
<div id="product-root"
     x-data="productBuy({ variants: @js($stockMap), length: @js($lengths->first()), hasLengths: {{ $lengths->isNotEmpty() ? 'true' : 'false' }} })">
  <div x-data="lightbox(@js($images->map(fn ($i) => ['src' => $i->url(2400), 'alt' => $i->alt ?: $product->name])->values()))">
    <div class="lg:grid lg:grid-cols-[minmax(0,1.4fr)_minmax(360px,1fr)] shop-w lg:pt-6 lg:px-8">

        {{-- ───── Gallery ───── --}}
        <div>
            {{-- Phones: swipe + dots --}}
            <div class="lg:hidden relative" x-data="swipeGallery">
                <div x-ref="track" class="flex overflow-x-auto snap-x snap-mandatory [scrollbar-width:none]" @scroll.throttle.50ms="onScroll($el)">
                    @forelse ($images as $image)
                        <button type="button" class="img-frame shrink-0 w-full aspect-[3/4] snap-center" @click="show({{ $loop->index }})" aria-label="Open image {{ $loop->iteration }} full screen">
                            <img src="{{ $image->url(1200) }}" srcset="{{ Img::srcset($image->path, [400, 800, 1200]) }}" sizes="100vw"
                                 alt="{{ $image->alt ?: $product->name.' – '.$colour->name }}" class="img-fade w-full h-full object-cover"
                                 @if ($loop->first) fetchpriority="high" @else loading="lazy" @endif>
                        </button>
                    @empty
                        <div class="img-frame w-full aspect-[3/4] grid place-items-center font-serif text-[28px] text-mute/40 px-6 text-center">{{ $product->name }}</div>
                    @endforelse
                </div>
                @if ($images->count() > 1)
                    <div class="absolute bottom-4 inset-x-0 flex justify-center gap-1.5">
                        @foreach ($images as $image)
                            <button type="button" @click="go({{ $loop->index }})" class="h-[3px] rounded-full transition-all duration-500"
                                    :class="active === {{ $loop->index }} ? 'w-6 bg-ink' : 'w-3 bg-ink/25'" aria-label="Image {{ $loop->iteration }}"></button>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Desktop: two-up grid of equal 3:4 photos (≈410×550 at full width).
                 Odd number of photos: the first one spans both columns (4:5) so the grid never ends on an empty cell.
                 A single photo stays the same size, centred in the gallery column. --}}
            @php $leadWide = $images->count() > 1 && $images->count() % 2 === 1; @endphp
            <div class="hidden lg:grid gap-1 {{ $images->count() > 1 ? 'grid-cols-2' : 'grid-cols-1 max-w-[560px] mx-auto' }}">
                @forelse ($images as $image)
                    @php $wide = $leadWide && $loop->first; @endphp
                    <button type="button" class="img-frame block cursor-zoom-in {{ $wide ? 'col-span-2 aspect-[4/5]' : 'aspect-[3/4]' }}"
                            @click="show({{ $loop->index }})" data-reveal="{{ $loop->first ? 'fade' : 'mask' }}" aria-label="Zoom image {{ $loop->iteration }}">
                        <img src="{{ $image->url(800) }}" srcset="{{ Img::srcset($image->path, [800, 1200, 1600, 2400]) }}"
                             sizes="{{ $wide ? '(min-width: 1440px) 840px, 60vw' : '(min-width: 1440px) 420px, 30vw' }}" alt="{{ $image->alt ?: $product->name.' – '.$colour->name }}"
                             class="img-fade w-full h-full object-cover transition-transform duration-[1400ms] ease-[var(--ease-out-quint)] hover:scale-[1.02]"
                             @if ($loop->first) fetchpriority="high" @else loading="lazy" decoding="async" @endif>
                    </button>
                @empty
                    <div class="img-frame aspect-[3/4] grid place-items-center font-serif text-[32px] text-mute/40">{{ $product->name }}</div>
                @endforelse
            </div>
        </div>

        {{-- ───── Details ───── --}}
        <div class="gutter lg:px-12 xl:px-16 pt-6 lg:pt-10 pb-10">
            <div class="lg:sticky lg:top-24">
                @if ($breadcrumbs)
                    <nav class="eyebrow text-[10px] text-mute flex flex-wrap gap-x-2 mb-8" aria-label="Breadcrumb" data-reveal>
                        @foreach ($breadcrumbs as $crumb)
                            <a href="{{ $crumb['url'] }}" class="link-draw">{{ $crumb['name'] }}</a>
                            @unless ($loop->last)<span>/</span>@endunless
                        @endforeach
                    </nav>
                @endif

                <div class="flex justify-between items-start gap-6">
                    <h1 class="h2" data-split>{{ $product->name }}</h1>
                    <button type="button" class="shrink-0 mt-2" x-data="wishlistButton({{ $product->id }}, {{ $colour->id }}, {{ $saved ? 'true' : 'false' }})"
                            @click="toggle()" :aria-pressed="saved.toString()" :aria-label="saved ? 'Remove from wishlist' : 'Save to wishlist'">
                        <svg data-heart class="w-6 h-6 transition-colors" viewBox="0 0 24 24" :fill="saved ? 'currentColor' : 'none'" fill="{{ $saved ? 'currentColor' : 'none' }}" stroke="currentColor" stroke-width="1.3">
                            <path d="M12 20.5s-7.5-4.6-9.3-9.2C1.4 8 3.5 4.5 7 4.5c2.1 0 3.6 1.2 5 3 1.4-1.8 2.9-3 5-3 3.5 0 5.6 3.5 4.3 6.8-1.8 4.6-9.3 9.2-9.3 9.2z"/>
                        </svg>
                        <span class="sr-only" x-text="saved ? 'In your wishlist' : 'Add to wishlist'">{{ $saved ? 'In your wishlist' : 'Add to wishlist' }}</span>
                    </button>
                </div>
                @if ($product->tagline)
                    <p class="text-mute mt-3" data-reveal style="--d: 200ms">{{ $product->tagline }}</p>
                @endif
                {{-- Where a price would sit: says clearly that this is a catalogue, not a missing price --}}
                <p class="eyebrow text-[10px] text-mute mt-4 flex items-center gap-3" data-reveal style="--d: 220ms">
                    <span class="w-6 h-px bg-line-strong"></span>Price on request
                </p>

                {{-- Colours --}}
                <div class="mt-8" data-reveal style="--d: 250ms">
                    <p class="label mb-3">Colour <span class="text-mute normal-case tracking-normal font-normal ml-1">{{ $colour->name }}</span></p>
                    @if ($product->colours->count() > 1)
                        <div class="flex flex-wrap gap-2">
                            @foreach ($product->colours as $c)
                                @php $thumb = $c->images->first(); @endphp
                                <a href="{{ $product->url($c) }}" data-swap title="{{ $c->name }}" aria-label="{{ $c->name }}"
                                   @if ($c->id === $colour->id) aria-current="true" @endif
                                   class="block w-14 p-[2px] border transition-colors duration-300 {{ $c->id === $colour->id ? 'border-ink' : 'border-transparent hover:border-line' }}">
                                    @if ($thumb)
                                        <img src="{{ $thumb->url(200) }}" alt="" loading="lazy" class="aspect-[3/4] object-cover w-full">
                                    @else
                                        <span class="block aspect-[3/4]" style="background: {{ $c->hex ?: '#ddd' }}"></span>
                                    @endif
                                </a>
                            @endforeach
                        </div>
                    @endif
                </div>

                {{-- Length --}}
                @if ($lengths->count() > 1)
                    <div class="mt-7" data-reveal style="--d: 300ms">
                        <p class="label mb-3">Length</p>
                        <div class="flex gap-1.5">
                            @foreach ($lengths as $l)
                                <button type="button" @click="length = @js($l); size = null" class="chip px-5" :class="length === @js($l) && 'is-on'">{{ $l }}</button>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- Sizes --}}
                @if ($sizes->isNotEmpty())
                    <div class="mt-7" data-reveal style="--d: 350ms" id="sizes">
                        <div class="flex justify-between mb-3">
                            <p class="label">Size <span class="text-mute normal-case tracking-normal font-normal ml-1" x-text="size || ''"></span></p>
                            @if ($sizeGuide)
                                <button type="button" class="label link-u text-mute" @click="$dispatch('open-size-guide')">Size guide</button>
                            @else
                                <a href="{{ route('shop.page', 'size-guide') }}" class="label link-u text-mute" target="_blank">Size guide</a>
                            @endif
                        </div>
                        <div class="grid grid-cols-4 sm:grid-cols-5 gap-1.5" x-ref="sizes">
                            @foreach ($sizes as $s)
                                <button type="button" class="chip" @click="if (inStock(@js($s))) { size = @js($s); message = '' }"
                                        :disabled="!inStock(@js($s))" :class="size === @js($s) && 'is-on'"
                                        :aria-pressed="(size === @js($s)).toString()">{{ $s }}</button>
                            @endforeach
                        </div>
                        <p class="text-[12px] mt-3 h-4" :class="message ? 'text-sale' : 'text-mute'">
                            <span x-show="message" x-text="message"></span>
                            <span x-show="!message && selected && selected.stock <= 3" x-cloak>Only <span x-text="selected?.stock"></span> left in this size</span>
                        </p>
                    </div>
                @endif

                <div class="{{ $sizes->isNotEmpty() ? 'mt-4' : 'mt-8' }} space-y-2" data-reveal style="--d: 400ms" x-ref="buy"
                     x-init="new IntersectionObserver(([e]) => $dispatch('buy-visible', e.isIntersecting)).observe($el)">
                    <button type="button" class="btn-black" @click="ask(@js($enquiry))">Ask for price</button>
                    @unless ($inStock)
                        <p class="text-[12px] text-mute">Currently sold out — ask us and we’ll let you know when it’s back.</p>
                    @endunless
                </div>

                <ul class="mt-6 space-y-1.5 text-[12px] text-mute" data-reveal style="--d: 450ms">
                    @foreach ($settings['product_notes'] ?? [] as $note)
                        <li class="flex gap-2"><span aria-hidden="true">—</span>{{ $note }}</li>
                    @endforeach
                </ul>

                {{-- Accordions --}}
                <div class="mt-10 border-t border-line" data-reveal style="--d: 500ms">
                    @if ($product->description || $details->isNotEmpty())
                        <div x-data="{ o: true }" class="border-b border-line">
                            <button type="button" @click="o = !o" class="w-full py-5 flex justify-between label" :aria-expanded="o">
                                Product description <span class="transition-transform duration-500" :class="o && 'rotate-45'">+</span>
                            </button>
                            <div x-show="o" x-collapse.duration.500ms>
                                <div class="pb-6 space-y-4 text-ink-2 leading-relaxed">
                                    @if ($product->description)<p>{{ $product->description }}</p>@endif
                                    @if ($details->isNotEmpty())
                                        <ul class="space-y-1">
                                            @foreach ($details as $line)<li class="flex gap-2"><span class="text-mute">—</span>{{ $line }}</li>@endforeach
                                        </ul>
                                    @endif
                                    @if ($product->model_info)<p class="text-mute whitespace-pre-line text-[13px]">{{ $product->model_info }}</p>@endif
                                    <p class="text-mute text-[12px]">Style ID: {{ $product->style_code }}-{{ $colour->code }}</p>
                                </div>
                            </div>
                        </div>
                    @endif
                    @if ($product->composition || $product->care)
                        <div x-data="{ o: false }" class="border-b border-line">
                            <button type="button" @click="o = !o" class="w-full py-5 flex justify-between label" :aria-expanded="o">
                                Composition &amp; care <span class="transition-transform duration-500" :class="o && 'rotate-45'">+</span>
                            </button>
                            <div x-show="o" x-collapse.duration.500ms x-cloak>
                                <div class="pb-6 space-y-2 text-ink-2">
                                    @if ($product->composition)<p>{{ $product->composition }}</p>@endif
                                    @if ($product->care)<p class="whitespace-pre-line text-mute">{{ $product->care }}</p>@endif
                                </div>
                            </div>
                        </div>
                    @endif
                    <div x-data="{ o: false }" class="border-b border-line">
                        <button type="button" @click="o = !o" class="w-full py-5 flex justify-between label" :aria-expanded="o">
                            Shipping &amp; returns <span class="transition-transform duration-500" :class="o && 'rotate-45'">+</span>
                        </button>
                        <div x-show="o" x-collapse.duration.500ms x-cloak>
                            <div class="pb-6 text-mute">
                                <p>{{ $settings['shipping_returns'] ?? '' }}</p>
                                @if ($shippingPage = $footerPages->flatten()->firstWhere('slug', 'shipping-returns'))
                                    <a href="{{ route('shop.page', $shippingPage->slug) }}" class="link-u label mt-3 inline-block text-ink">Read more</a>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ───── Sticky "Ask for price" bar (phones, when the main button is off screen) ───── --}}
        <div x-data="{ show: false }" @buy-visible.window="show = !$event.detail" x-show="show" x-cloak
             x-transition:enter="transition duration-500 ease-[var(--ease-out-quint)]" x-transition:enter-start="translate-y-full"
             x-transition:leave="transition duration-300" x-transition:leave-end="translate-y-full"
             class="lg:hidden fixed bottom-0 inset-x-0 z-40 bg-paper/95 backdrop-blur-md border-t border-line px-4 py-3 pb-[max(12px,env(safe-area-inset-bottom))] flex items-center gap-3">
            <div class="flex-1 min-w-0">
                <p class="label truncate">{{ $product->name }}</p>
                <p class="text-[12px] text-mute">{{ $colour->name }} <span x-text="size ? '· ' + size : ''"></span></p>
            </div>
            <button type="button" class="btn-black w-auto px-6" @click="ask(@js($enquiry))">Ask for price</button>
        </div>

    {{-- ───── Full-screen zoom ───── --}}
    <template x-teleport="body">
        <div x-show="open" x-cloak class="fixed inset-0 z-[95] bg-paper" role="dialog" aria-modal="true" aria-label="Image viewer"
             @keydown.escape.window="close()" @keydown.arrow-right.window="open && next(1)" @keydown.arrow-left.window="open && next(-1)"
             x-transition:enter="transition duration-500 ease-[var(--ease-out-quint)]" x-transition:enter-start="opacity-0 scale-[1.02]"
             x-transition:leave="transition duration-300" x-transition:leave-end="opacity-0">
            <div class="absolute inset-0 overflow-auto touch-pinch-zoom flex items-center justify-center" data-lenis-prevent
                 @mousemove="pan($event)" @click="zoom = !zoom">
                <template x-for="(img, i) in images" :key="i">
                    <img x-show="index === i" :src="img.src" :alt="img.alt"
                         class="max-h-full max-w-full object-contain transition-transform duration-500 ease-[var(--ease-out-quint)] select-none"
                         :class="zoom ? 'scale-[2.2] cursor-zoom-out' : 'cursor-zoom-in'">
                </template>
            </div>
            <div class="absolute top-0 inset-x-0 flex justify-between items-center gutter h-16 label">
                <span x-text="(index + 1) + ' / ' + images.length"></span>
                <button type="button" class="link-draw" @click.stop="close()">Close</button>
            </div>
            <template x-if="images.length > 1">
                <div>
                    <button type="button" class="absolute left-4 top-1/2 -translate-y-1/2 w-12 h-12 grid place-items-center bg-paper/80 hover:bg-paper" @click.stop="next(-1)" aria-label="Previous">←</button>
                    <button type="button" class="absolute right-4 top-1/2 -translate-y-1/2 w-12 h-12 grid place-items-center bg-paper/80 hover:bg-paper" @click.stop="next(1)" aria-label="Next">→</button>
                </div>
            </template>
        </div>
    </template>
  </div>
</div>

{{-- ───── Size guide drawer ───── --}}
@if ($sizeGuide)
    <div x-data="{ open: false }" @open-size-guide.window="open = true" @keydown.escape.window="open = false"
         x-show="open" x-cloak class="fixed inset-0 z-[90]" role="dialog" aria-modal="true" aria-label="Size guide"
         x-effect="document.documentElement.style.overflow = open ? 'hidden' : ''">
        <div class="scrim" @click="open = false" x-show="open" x-transition.opacity.duration.400ms></div>
        <div class="drawer max-w-xl" x-show="open"
             x-transition:enter="transition duration-500 ease-[var(--ease-out-quint)]" x-transition:enter-start="translate-x-full"
             x-transition:leave="transition duration-300" x-transition:leave-end="translate-x-full">
            <div class="drawer-head">
                <span class="label">Size guide — {{ $sizeGuide->name }}</span>
                <button type="button" class="label link-draw" @click="open = false">Close</button>
            </div>
            <div class="flex-1 overflow-y-auto px-6 py-8 rich" data-lenis-prevent>{!! $sizeGuide->bodyHtml() !!}</div>
        </div>
    </div>
@endif

{{-- ───── Complete the look ───── --}}
@if ($related->isNotEmpty())
    <section class="section" data-rail-group>
        @include('shop.partials.section-head', ['title' => 'Complete the look', 'eyebrow' => 'Pairs well with', 'arrows' => true])
        <div class="relative">
            <div class="rail edge" data-rail data-stagger="80">
                @foreach ($related as $product)
                    <div class="rail-card">
                        @include('shop.partials.product-card', ['sizes' => '(min-width: 1440px) 340px, (min-width: 1280px) 24vw, (min-width: 768px) 31vw, 72vw'])
                    </div>
                @endforeach
            </div>
            <div class="edge"><div class="h-px bg-line relative overflow-hidden"><span data-rail-progress class="absolute inset-y-0 left-0 bg-ink transition-transform duration-150"></span></div></div>
        </div>
    </section>
@endif
@endsection

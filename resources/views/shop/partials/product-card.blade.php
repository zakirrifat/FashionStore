@php
    use App\Http\Controllers\ShopController;
    $colour = $product->colours->first();
    $images = $colour?->images ?? collect();
    $first = $images->get(0);
    $second = $images->get(1);
    $sizes = $sizes ?? '(min-width: 1280px) 25vw, (min-width: 768px) 33vw, 50vw';
    $variants = $colour && $colour->relationLoaded('variants')
        ? $colour->variants->sortBy(fn ($v) => ShopController::sizeRank($v->size))->values()
        : collect();
    $soldOut = $variants->isNotEmpty() && $variants->sum('stock') === 0;
    $isNew = $product->created_at && $product->created_at->gt(now()->subDays(30));
    $enquiry = [
        'id' => $product->id, 'name' => $product->name, 'image' => $first?->url(400),
        'colourId' => $colour?->id, 'colour' => $colour?->name,
    ];
@endphp
<article class="group relative" x-data>
    <div class="relative">
        <a href="{{ $product->url($colour) }}" class="block" aria-label="{{ $product->name }}">
            <div class="img-frame aspect-[3/4]">
                @if ($first)
                    <img src="{{ $first->url(800) }}" srcset="{{ $first->srcset() }}" sizes="{{ $sizes }}"
                         alt="{{ $first->alt ?: $product->name }}" loading="lazy" decoding="async"
                         class="img-fade absolute inset-0 w-full h-full object-cover transition-[opacity,transform] duration-[900ms] ease-[var(--ease-out-quint)] group-hover:scale-[1.025] {{ $second ? 'lg:group-hover:opacity-0' : '' }}">
                    @if ($second)
                        <img src="{{ $second->url(800) }}" srcset="{{ $second->srcset() }}" sizes="{{ $sizes }}"
                             alt="" loading="lazy" decoding="async" aria-hidden="true"
                             class="hidden lg:block absolute inset-0 w-full h-full object-cover opacity-0 scale-[1.04] transition-[opacity,transform] duration-[900ms] ease-[var(--ease-out-quint)] group-hover:opacity-100 group-hover:scale-100">
                    @endif
                @else
                    <div class="absolute inset-0 grid place-items-center font-serif text-[22px] text-mute/40 px-4 text-center">{{ $product->name }}</div>
                @endif

                {{-- Badges --}}
                <div class="absolute top-3 left-3 flex gap-1.5">
                    @if ($soldOut)
                        <span class="bg-paper/90 backdrop-blur px-2 py-1 eyebrow text-[9px]">Sold out</span>
                    @elseif ($isNew)
                        <span class="bg-paper/90 backdrop-blur px-2 py-1 eyebrow text-[9px]">New</span>
                    @endif
                </div>
            </div>
        </a>

        {{-- Ask for price: slides up over the bottom of the photo on hover (desktop).
             Anchored to the photo, so it sits at the same height on every card. --}}
        <div class="hidden lg:block absolute inset-x-2 bottom-2 translate-y-3 opacity-0 pointer-events-none group-hover:translate-y-0 group-hover:opacity-100 group-hover:pointer-events-auto focus-within:translate-y-0 focus-within:opacity-100 focus-within:pointer-events-auto transition-all duration-500 ease-[var(--ease-out-quint)]">
            <button type="button" class="btn-black" @click="$store.enquiry.open(@js($enquiry))">Ask for price</button>
        </div>
    </div>

    <div class="pt-3 pb-5">
        <div class="flex justify-between gap-3">
            <a href="{{ $product->url($colour) }}" class="min-w-0">
                <h3 class="text-[13px] leading-snug truncate"><span class="link-draw">{{ $product->name }}</span></h3>
            </a>
            <button type="button" class="lg:hidden shrink-0 text-[12px] link-u whitespace-nowrap"
                    @click="$store.enquiry.open(@js($enquiry))">Ask for price</button>
        </div>
        {{-- Second line (always present, so cards in a row line up): tagline or colour name, swatches on the right --}}
        <div class="flex items-center justify-between gap-3 mt-1 min-h-[18px] text-[12px] text-mute">
            <p class="truncate min-w-0">{{ $product->tagline ?: $colour?->name }}</p>
            @if ($product->colours_count > 1)
                <p class="flex items-center gap-1 shrink-0" aria-label="{{ $product->colours_count }} colours">
                    @foreach ($product->colours->take(4) as $c)
                        <span class="w-2.5 h-2.5 rounded-full border border-line" style="background: {{ $c->hex ?: '#d9d5cc' }}" title="{{ $c->name }}"></span>
                    @endforeach
                    @if ($product->colours_count > 4)
                        <span class="ml-0.5">+{{ $product->colours_count - 4 }}</span>
                    @endif
                </p>
            @endif
        </div>
    </div>
</article>

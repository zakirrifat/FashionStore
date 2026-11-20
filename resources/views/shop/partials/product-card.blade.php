@php
    use App\Support\Money;
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
@endphp
<article class="group relative">
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
                <div class="absolute inset-0 grid place-items-center font-serif text-[22px] text-[#cfcbc1] px-4 text-center">{{ $product->name }}</div>
            @endif

            {{-- Badges --}}
            <div class="absolute top-3 left-3 flex gap-1.5">
                @if ($soldOut)
                    <span class="bg-paper/90 backdrop-blur px-2 py-1 eyebrow text-[9px]">Sold out</span>
                @elseif ($product->isOnSale())
                    <span class="bg-sale text-paper px-2 py-1 eyebrow text-[9px]">Sale</span>
                @elseif ($isNew)
                    <span class="bg-paper/90 backdrop-blur px-2 py-1 eyebrow text-[9px]">New</span>
                @endif
            </div>
        </div>
    </a>

    {{-- Quick add: sizes slide up on hover (desktop) --}}
    @if ($variants->isNotEmpty() && ! $soldOut)
        <div class="hidden lg:block absolute inset-x-2 bottom-[68px] translate-y-3 opacity-0 pointer-events-none group-hover:translate-y-0 group-hover:opacity-100 group-hover:pointer-events-auto transition-all duration-500 ease-[var(--ease-out-quint)]"
             x-data="{ busy: null }">
            <div class="bg-paper/95 backdrop-blur-md px-3 py-2.5">
                <p class="eyebrow text-[9px] text-mute mb-2">Quick add</p>
                <div class="flex flex-wrap gap-1">
                    @foreach ($variants as $v)
                        <button type="button" class="chip h-8 min-w-8 px-2 text-[11px]" @disabled($v->stock < 1)
                                :class="busy === {{ $v->id }} && 'is-on'"
                                @click="busy = {{ $v->id }}; await $store.bag.add({{ $v->id }}); busy = null"
                                aria-label="Add size {{ trim($v->size.' '.$v->length) }} to bag">{{ $v->size }}{{ $v->length ? ' '.substr($v->length, 0, 1) : '' }}</button>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    <a href="{{ $product->url($colour) }}" class="block pt-3 pb-8">
        <div class="flex justify-between gap-3">
            <h3 class="text-[13px] leading-snug truncate"><span class="link-draw">{{ $product->name }}</span></h3>
            <p class="text-[13px] whitespace-nowrap text-right">
                @if ($product->isOnSale())
                    <span class="text-sale">{{ Money::format($product->price) }}</span>
                    <s class="text-mute ml-1">{{ Money::format($product->compare_at_price) }}</s>
                @else
                    {{ Money::format($product->price) }}
                @endif
            </p>
        </div>
        @if ($product->colours_count > 1)
            <p class="flex items-center gap-1.5 mt-1.5">
                @foreach ($product->colours->take(5) as $c)
                    <span class="w-2.5 h-2.5 rounded-full border border-line" style="background: {{ $c->hex ?: '#d9d5cc' }}" title="{{ $c->name }}"></span>
                @endforeach
                <span class="text-mute text-[12px]">{{ $product->colours_count }} colours</span>
            </p>
        @endif
    </a>
</article>

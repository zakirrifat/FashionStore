@php use App\Support\Money; use App\Support\Img; @endphp
@if ($lines->isEmpty())
    <div class="flex-1 grid place-items-center text-center px-8">
        <div>
            <p class="h2 mb-3">Your bag is empty</p>
            <p class="text-mute mb-8">Pieces you add will appear here.</p>
            <a href="{{ route('shop.home') }}" class="btn-line">Continue shopping</a>
        </div>
    </div>
@else
    <ul class="flex-1 overflow-y-auto px-6 divide-y divide-line" data-lenis-prevent>
        @foreach ($lines as $i => $line)
            <li class="py-5 flex gap-4 {{ $line->available ? '' : 'opacity-50' }}" style="animation: vt-in 600ms var(--ease-out-quint) {{ $i * 60 }}ms both">
                <a href="{{ $line->product->url($line->colour) }}" class="img-frame w-[84px] shrink-0 aspect-[3/4]">
                    @if ($line->image)<img src="{{ Img::url($line->image, 200) }}" alt="" class="w-full h-full object-cover">@endif
                </a>
                <div class="flex-1 min-w-0 flex flex-col">
                    <div class="flex justify-between gap-3">
                        <a href="{{ $line->product->url($line->colour) }}" class="label leading-snug">{{ $line->product->name }}</a>
                        <span class="whitespace-nowrap text-[13px]">{{ Money::format($line->unitPrice * min($line->quantity, max(1, $line->stock))) }}</span>
                    </div>
                    <p class="text-mute text-[13px] mt-1">{{ $line->colour->name }} · {{ trim($line->variant->size.' '.$line->variant->length) }}</p>
                    @unless ($line->available)<p class="text-sale text-[12px] mt-1">No longer available</p>@endunless
                    <div class="mt-auto pt-3 flex items-center justify-between">
                        @if ($line->available)
                            <form method="post" action="{{ route('bag.update', $line->item->id) }}">
                                @csrf <input type="hidden" name="_method" value="PATCH">
                                <label class="sr-only" for="dq-{{ $line->item->id }}">Quantity</label>
                                <select id="dq-{{ $line->item->id }}" name="quantity" data-qty class="field-input w-16 py-1 text-[13px]">
                                    @foreach (range(1, max(1, min(\App\Support\ShoppingCart::MAX_PER_LINE, $line->stock))) as $q)
                                        <option value="{{ $q }}" @selected($q === min($line->quantity, $line->stock))>{{ $q }}</option>
                                    @endforeach
                                </select>
                            </form>
                        @else
                            <span></span>
                        @endif
                        <form method="post" action="{{ route('bag.remove', $line->item->id) }}">
                            @csrf <input type="hidden" name="_method" value="DELETE">
                            <button class="label text-mute link-draw">Remove</button>
                        </form>
                    </div>
                </div>
            </li>
        @endforeach
    </ul>
    <div class="border-t border-line p-6 space-y-4 bg-paper">
        <div class="flex justify-between label text-[12px]">
            <span>Subtotal</span><span>{{ Money::format($totals->subtotal - $totals->discount) }}</span>
        </div>
        <p class="text-mute text-[12px]">Delivery calculated at checkout. Payment by bank transfer after you order.</p>
        <a href="{{ route('checkout') }}" class="btn-black">Checkout</a>
        <a href="{{ route('bag') }}" class="btn-line">View bag</a>
    </div>
@endif

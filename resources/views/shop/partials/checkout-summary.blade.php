@php use App\Support\Money; use App\Support\Img; @endphp
<ul class="space-y-3 mb-4">
    @foreach ($totals->lines as $p)
        <li class="flex gap-3">
            <div class="w-14 shrink-0 aspect-[3/4] bg-paper-2 overflow-hidden relative">
                @if ($p->line->image)
                    <img src="{{ Img::url($p->line->image, 200) }}" alt="" class="w-full h-full object-cover">
                @endif
                <span class="absolute -top-1 -right-1 bg-ink text-paper text-[10px] rounded-full w-5 h-5 grid place-items-center">{{ $p->quantity }}</span>
            </div>
            <div class="flex-1 min-w-0">
                <p class="label truncate">{{ $p->line->product->name }}</p>
                <p class="text-mute text-[12px]">{{ $p->line->colour->name }} · {{ trim($p->line->variant->size.' '.$p->line->variant->length) }}</p>
            </div>
            <span class="whitespace-nowrap">{{ Money::format($p->lineTotal) }}</span>
        </li>
    @endforeach
</ul>
<dl class="space-y-2 border-t border-line pt-3">
    <div class="flex justify-between"><dt>Subtotal</dt><dd>{{ Money::format($totals->subtotal) }}</dd></div>
    @if ($totals->discount)
        <div class="flex justify-between"><dt>Discount ({{ $totals->discountCode }})</dt><dd>−{{ Money::format($totals->discount) }}</dd></div>
    @elseif ($totals->discountProblem)
        <div class="text-sale text-[12px]">Discount code: {{ $totals->discountProblem }}</div>
    @endif
    <div class="flex justify-between">
        <dt>Delivery{{ $totals->shippingMethod ? ' · '.$totals->shippingMethod->name : '' }}</dt>
        <dd>{{ $totals->shippingMethod ? ($totals->shipping ? Money::format($totals->shipping) : 'Free') : '—' }}</dd>
    </div>
    @if ($totals->giftWrap || request()->boolean('gift_wrap'))
        <div class="flex justify-between"><dt>Gift wrapping</dt><dd>{{ $totals->giftWrap ? Money::format($totals->giftWrap) : 'Free' }}</dd></div>
    @endif
    <div class="flex justify-between label text-[13px] border-t border-line pt-3">
        <dt>Total</dt><dd>{{ Money::format($totals->total) }}</dd>
    </div>
    <p class="text-mute text-[12px]">
        @if ($totals->vatExempt)
            Shipped outside South Africa: VAT ({{ $totals->vatRate }}%) removed. Import duties may apply in your country.
        @else
            Includes VAT of {{ Money::format($totals->vat) }}.
        @endif
    </p>
</dl>

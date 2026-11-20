@extends('shop.layout')
@php use App\Support\Money; use App\Support\Img; @endphp

@section('title', 'Bag')

@section('content')
<div class="gutter container-x pt-12 md:pt-16">
    <h1 class="h1 mb-10" data-split>Bag <span class="text-mute">({{ $lines->sum('quantity') }})</span></h1>
    @include('shop.partials.status')

    @if ($lines->isEmpty())
        <div class="py-24 text-center max-w-md mx-auto">
            <p class="h2 mb-3" data-reveal>Your bag is empty</p>
            <p class="text-mute mb-10" data-reveal style="--d: 100ms">Add something you love and it will appear here.</p>
            <div data-reveal style="--d: 200ms"><a href="{{ route('shop.home') }}" class="btn-line">Continue shopping</a></div>
        </div>
    @else
        <div class="lg:grid lg:grid-cols-[1fr_400px] lg:gap-16">
            <ul class="border-t border-line" data-stagger="70">
                @foreach ($lines as $line)
                    <li class="border-b border-line py-6 flex gap-5 {{ $line->available ? '' : 'opacity-60' }}">
                        <a href="{{ $line->product->url($line->colour) }}" class="img-frame w-28 md:w-36 shrink-0 aspect-[3/4] group">
                            @if ($line->image)
                                <img src="{{ Img::url($line->image, 400) }}" alt="" class="img-fade w-full h-full object-cover transition-transform duration-1000 group-hover:scale-[1.04]">
                            @endif
                        </a>
                        <div class="flex-1 min-w-0 flex flex-col">
                            <div class="flex justify-between gap-4">
                                <a href="{{ $line->product->url($line->colour) }}" class="h3"><span class="link-draw">{{ $line->product->name }}</span></a>
                                <span class="whitespace-nowrap">{{ Money::format($line->unitPrice * $line->quantity) }}</span>
                            </div>
                            <p class="text-mute mt-1.5">{{ $line->colour->name }} · {{ trim($line->variant->size.' '.$line->variant->length) }}</p>
                            @unless ($line->available)
                                <p class="text-sale text-[12px] mt-2">No longer available — please remove.</p>
                            @endunless
                            @if ($line->tooMany)
                                <p class="text-sale text-[12px] mt-2">Only {{ $line->stock }} left — your quantity will be reduced.</p>
                            @elseif ($line->available && $line->stock <= 3)
                                <p class="text-mute text-[12px] mt-2">Only {{ $line->stock }} left</p>
                            @endif
                            <div class="mt-auto pt-4 flex items-center gap-6">
                                @if ($line->available)
                                    <form method="post" action="{{ route('bag.update', $line->item->id) }}" x-data>
                                        @csrf @method('PATCH')
                                        <label class="sr-only" for="qty-{{ $line->item->id }}">Quantity</label>
                                        <select id="qty-{{ $line->item->id }}" name="quantity" @change="$el.form.submit()" class="field-input w-20 py-1">
                                            @foreach (range(1, max(1, min(\App\Support\ShoppingCart::MAX_PER_LINE, $line->stock))) as $q)
                                                <option value="{{ $q }}" @selected($q === min($line->quantity, $line->stock))>Qty {{ $q }}</option>
                                            @endforeach
                                        </select>
                                        <noscript><button class="label link-u">Update</button></noscript>
                                    </form>
                                @endif
                                <form method="post" action="{{ route('bag.remove', $line->item->id) }}">
                                    @csrf @method('DELETE')
                                    <button class="label link-draw text-mute">Remove</button>
                                </form>
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>

            <aside class="mt-10 lg:mt-0" data-reveal style="--d: 150ms">
                <div class="lg:sticky lg:top-24 bg-paper-2/60 p-6 md:p-8 space-y-5">
                    <p class="label">Summary</p>
                    <dl class="space-y-3">
                        <div class="flex justify-between"><dt>Subtotal</dt><dd>{{ Money::format($totals->subtotal) }}</dd></div>
                        @if ($totals->discount)
                            <div class="flex justify-between text-ok"><dt>Discount ({{ $totals->discountCode }})</dt><dd>−{{ Money::format($totals->discount) }}</dd></div>
                        @endif
                        <div class="flex justify-between text-mute"><dt>Delivery</dt><dd>At checkout</dd></div>
                        <div class="flex justify-between border-t border-line pt-4 text-[16px]">
                            <dt>Total</dt><dd>{{ Money::format($totals->subtotal - $totals->discount) }}</dd>
                        </div>
                        <p class="text-mute text-[12px]">Prices include VAT for South Africa. Orders shipped abroad are charged without VAT.</p>
                    </dl>

                    @if ($discountCode && ! $totals->discountProblem)
                        <form method="post" action="{{ route('bag.discount.remove') }}" class="flex justify-between items-center border border-line bg-paper px-4 py-3">
                            @csrf @method('DELETE')
                            <span class="label">Code {{ $discountCode }} ✓</span>
                            <button class="label link-draw text-mute">Remove</button>
                        </form>
                    @else
                        <form method="post" action="{{ route('bag.discount') }}" class="flex gap-3 items-end">
                            @csrf
                            <div class="flex-1"><x-shop.input name="code" label="Discount code" :value="$discountCode" /></div>
                            <button class="label link-draw pb-2">Apply</button>
                        </form>
                        @if ($discountCode && $totals->discountProblem)
                            <p class="text-sale text-[12px]">{{ $totals->discountProblem }}</p>
                        @endif
                    @endif

                    @if ($lines->where('available', true)->isNotEmpty())
                        <a href="{{ route('checkout') }}" class="btn-black">Checkout</a>
                    @endif
                    <a href="{{ route('shop.home') }}" class="btn-line">Continue shopping</a>
                    <p class="text-mute text-[12px]">Payment is by bank transfer. After you order, we’ll contact you with payment details.</p>
                </div>
            </aside>
        </div>
    @endif
</div>
@endsection

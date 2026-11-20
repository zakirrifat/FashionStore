@extends('shop.layout')
@php use App\Support\Money; use App\Support\Img; @endphp

@section('title', 'Order '.$order->number)

@push('head')
    <meta name="robots" content="noindex">
@endpush

@section('content')
<div class="gutter max-w-5xl mx-auto pt-16 md:pt-24">
    <header class="text-center mb-14">
        @if ($justPlaced)
            <svg class="draw-check w-16 h-16 mx-auto mb-8" viewBox="0 0 52 52" fill="none" stroke="currentColor" stroke-width="1.2" aria-hidden="true">
                <circle cx="26" cy="26" r="25"/><path d="M15 27l7 7 15-16"/>
            </svg>
            <p class="eyebrow text-mute mb-4" data-reveal>Thank you</p>
            <h1 class="h1" data-split data-delay="200">Your order has been received</h1>
            <p class="lead text-mute mt-5" data-reveal style="--d: 500ms">A confirmation has been sent to {{ $order->email }}.</p>
        @else
            <p class="eyebrow text-mute mb-4" data-reveal>Your order</p>
            <h1 class="h1" data-split>Order {{ $order->number }}</h1>
        @endif
    </header>

    {{-- Status --}}
    <section class="bg-paper-2/60 p-6 md:p-10 mb-14" data-reveal style="--d: 300ms">
        <div class="grid grid-cols-3 gap-4 pb-6 border-b border-line">
            <div><p class="eyebrow text-[10px] text-mute mb-2">Order number</p><p class="text-[18px]">{{ $order->number }}</p></div>
            <div><p class="eyebrow text-[10px] text-mute mb-2">Status</p><p class="text-[18px]">{{ $order->statusLabel() }}</p></div>
            <div><p class="eyebrow text-[10px] text-mute mb-2">Total</p><p class="text-[18px]">{{ Money::format($order->total) }}</p></div>
        </div>

        {{-- Progress line --}}
        @php
            $steps = ['awaiting_payment' => 'Order placed', 'paid' => 'Paid', 'shipped' => 'Shipped', 'delivered' => 'Delivered'];
            $reached = array_search($order->status, array_keys($steps), true);
        @endphp
        @if ($order->status !== 'cancelled')
            <ol class="grid grid-cols-4 gap-2 pt-6">
                @foreach ($steps as $key => $label)
                    <li>
                        <div class="h-[2px] {{ $loop->index <= $reached ? 'bg-ink' : 'bg-line' }}"></div>
                        <p class="eyebrow text-[10px] mt-2 {{ $loop->index <= $reached ? '' : 'text-mute' }}">{{ $label }}</p>
                    </li>
                @endforeach
            </ol>
        @endif

        <div class="pt-6 text-[15px] leading-relaxed">
            @switch($order->status)
                @case('awaiting_payment')
                    <p><strong class="font-medium">Next step: payment by bank transfer.</strong> We’ll contact you by email or phone shortly with the payment details.
                        Please use <strong class="font-medium">{{ $order->number }}</strong> as your payment reference. Your items are reserved for you.</p>
                    @if (filled($paymentInstructions))
                        <div class="mt-5 bg-paper border border-line p-5 whitespace-pre-line text-[14px]">{{ $paymentInstructions }}</div>
                    @endif
                    @break
                @case('paid')
                    <p>Payment received — thank you. We’re preparing your order.</p>
                    @break
                @case('shipped')
                    <p>Your order is on its way{{ $order->courier ? ' with '.$order->courier : '' }}.
                        @if ($order->tracking_number) Tracking number: <strong class="font-medium">{{ $order->tracking_number }}</strong>. @endif
                        @if ($order->tracking_url) <a href="{{ $order->tracking_url }}" target="_blank" rel="noopener" class="link-u">Track your parcel</a> @endif
                    </p>
                    @break
                @case('delivered')
                    <p>Delivered. We hope you love it.</p>
                    @break
                @case('cancelled')
                    <p>This order was cancelled. Questions? <a href="{{ route('shop.contact') }}" class="link-u">Contact us</a>.</p>
                    @break
            @endswitch
        </div>
    </section>

    <div class="grid md:grid-cols-[1fr_300px] gap-14">
        <section data-reveal>
            <h2 class="label mb-5">Items</h2>
            <ul class="border-t border-line">
                @foreach ($order->items as $item)
                    <li class="border-b border-line py-4 flex gap-5">
                        <div class="img-frame w-20 shrink-0 aspect-[3/4]">
                            @if ($item->image)<img src="{{ Img::url($item->image, 200) }}" alt="" class="w-full h-full object-cover">@endif
                        </div>
                        <div class="flex-1">
                            <p class="h3">{{ $item->name }}</p>
                            <p class="text-mute mt-1">{{ $item->colour }} · {{ $item->sizeLabel() }} · Qty {{ $item->quantity }}</p>
                        </div>
                        <p class="whitespace-nowrap">{{ Money::format($item->line_total) }}</p>
                    </li>
                @endforeach
            </ul>
            <dl class="space-y-2.5 pt-6 max-w-sm ml-auto">
                <div class="flex justify-between"><dt>Subtotal</dt><dd>{{ Money::format($order->subtotal) }}</dd></div>
                @if ($order->discount_total)
                    <div class="flex justify-between text-ok"><dt>Discount ({{ $order->discount_code }})</dt><dd>−{{ Money::format($order->discount_total) }}</dd></div>
                @endif
                <div class="flex justify-between"><dt>Delivery</dt><dd>{{ $order->shipping_total ? Money::format($order->shipping_total) : 'Free' }}</dd></div>
                @if ($order->gift_wrap)
                    <div class="flex justify-between"><dt>Gift wrapping</dt><dd>{{ $order->gift_wrap_total ? Money::format($order->gift_wrap_total) : 'Free' }}</dd></div>
                @endif
                <div class="flex justify-between border-t border-line pt-3 text-[16px]"><dt>Total</dt><dd>{{ Money::format($order->total) }}</dd></div>
                <p class="text-mute text-[12px]">{{ $order->vat_exempt ? 'Export order: no South African VAT charged.' : 'Includes VAT of '.Money::format($order->vat_total).'.' }}</p>
            </dl>
        </section>
        <aside class="space-y-8" data-reveal style="--d: 150ms">
            <div>
                <h2 class="label mb-3">Delivery to</h2>
                <address class="not-italic text-mute leading-relaxed">
                    @foreach ($order->addressLines() as $line)<span class="block">{{ $line }}</span>@endforeach
                </address>
                <p class="text-mute mt-3">{{ $order->shipping_method }}</p>
            </div>
            <div>
                <h2 class="label mb-3">Contact</h2>
                <p class="text-mute">{{ $order->email }}<br>{{ $order->phone }}</p>
            </div>
            @if ($order->gift_wrap)
                <div>
                    <h2 class="label mb-3">Gift</h2>
                    <p class="text-mute">Gift wrapped{{ $order->gift_message ? ': “'.$order->gift_message.'”' : '' }}</p>
                </div>
            @endif
        </aside>
    </div>

    <div class="flex flex-wrap justify-center gap-3 mt-16" data-reveal>
        <a href="{{ route('shop.home') }}" class="btn-dark-outline px-10">Continue shopping</a>
        @guest
            @if ($justPlaced)
                <a href="{{ route('register') }}" class="btn-black w-auto px-10">Create an account</a>
            @endif
        @endguest
    </div>
</div>
@endsection

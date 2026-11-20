<x-mail::message>
@switch($event)
@case('paid')
# Payment received

Hi {{ $order->shipping_address['first_name'] ?? '' }}, thank you — we’ve received payment for order **{{ $order->number }}**. We’re now preparing it and will let you know when it ships.
@break
@case('shipped')
# Your order is on its way

Order **{{ $order->number }}** has been sent with {{ $order->courier ?: 'our courier' }}.

@if ($order->tracking_number)
Tracking number: **{{ $order->tracking_number }}**
@endif

@if ($order->tracking_url)
<x-mail::button :url="$order->tracking_url">
Track your parcel
</x-mail::button>
@endif
@break
@case('cancelled')
# Order cancelled

Order **{{ $order->number }}** has been cancelled. If you’ve already paid, we’ll refund you in full. If you didn’t expect this, please reply to this email.
@break
@endswitch

@include('mail.partials.order-summary')

<x-mail::button :url="$order->confirmationUrl()" color="primary">
View your order
</x-mail::button>

{{ config('app.name') }}
</x-mail::message>

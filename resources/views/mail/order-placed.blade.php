<x-mail::message>
# Thank you for your order

Hi {{ $order->shipping_address['first_name'] ?? '' }},

We’ve received your order **{{ $order->number }}**.

**What happens next:** we’ll contact you by email or phone to arrange payment by bank transfer. Please use **{{ $order->number }}** as the payment reference. Your items are reserved for you, and we’ll send them as soon as payment is received.

@if (filled($paymentInstructions))
<x-mail::panel>
{!! nl2br(e($paymentInstructions)) !!}
</x-mail::panel>
@endif

@include('mail.partials.order-summary')

<x-mail::button :url="$order->confirmationUrl()">
View your order
</x-mail::button>

Questions? Just reply to this email.

{{ config('app.name') }}
</x-mail::message>

<x-mail::message>
# New order {{ $order->number }}

**{{ $order->customerName() }}** · {{ $order->email }} · {{ $order->phone }}

Contact the customer to arrange payment by bank transfer, then mark the order as **Paid** in the admin.

@if ($order->customer_note)
**Customer note:** {{ $order->customer_note }}
@endif
@if ($order->gift_wrap)
**Gift wrap** @if ($order->gift_message) — message: “{{ $order->gift_message }}” @endif
@endif

@include('mail.partials.order-summary')

<x-mail::button :url="url('/admin/orders/'.$order->id)">
Open in admin
</x-mail::button>
</x-mail::message>

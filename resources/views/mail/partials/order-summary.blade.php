@php use App\Support\Money; @endphp
<x-mail::table>
| Item | Qty | Price |
|:-----|:---:|------:|
@foreach ($order->items as $item)
| {{ $item->name }}<br><small>{{ $item->colour }} · {{ $item->sizeLabel() }}</small> | {{ $item->quantity }} | {{ Money::format($item->line_total) }} |
@endforeach
| Subtotal | | {{ Money::format($order->subtotal) }} |
@if ($order->discount_total)
| Discount ({{ $order->discount_code }}) | | −{{ Money::format($order->discount_total) }} |
@endif
| Delivery: {{ $order->shipping_method }} | | {{ $order->shipping_total ? Money::format($order->shipping_total) : 'Free' }} |
@if ($order->gift_wrap)
| Gift wrapping | | {{ $order->gift_wrap_total ? Money::format($order->gift_wrap_total) : 'Free' }} |
@endif
| **Total** | | **{{ Money::format($order->total) }}** |
</x-mail::table>

@if ($order->vat_exempt)
<small>Export order: South African VAT is not charged. Import duties and taxes in your country may apply on delivery.</small>
@else
<small>Includes VAT of {{ Money::format($order->vat_total) }}.</small>
@endif

**Delivery address**<br>
{!! collect($order->addressLines())->map(fn ($l) => e($l))->implode('<br>') !!}

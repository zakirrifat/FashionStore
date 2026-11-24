<x-mail::message>
# New price request

**{{ $enquiry->name }}** would like to know the price of:

@if ($productUrl)
**[{{ $enquiry->product_name }}]({{ $productUrl }})**
@else
**{{ $enquiry->product_name }}**
@endif
@if ($enquiry->optionsLabel())
{{ $enquiry->optionsLabel() }}
@endif
@if ($enquiry->sku)
SKU: {{ $enquiry->sku }}
@endif

<x-mail::panel>
**Phone:** [{{ $enquiry->phone }}](tel:{{ $tel }})<br>
**Email:** {{ $enquiry->email ?: '—' }}
@if ($enquiry->message)

**Message:**<br>
{{ $enquiry->message }}
@endif
</x-mail::panel>

Contact the customer with the price, then mark the request as **Contacted** in the admin.

<x-mail::button :url="$adminUrl">
Open price requests
</x-mail::button>
</x-mail::message>

@props(['url'])
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
@if ($logo = \App\Support\Brand::url('logo_dark'))
<img src="{{ $logo }}" alt="{{ config('app.name') }}" style="height: 56px; width: auto; max-width: 260px;">
@else
{!! $slot !!}
@endif
</a>
</td>
</tr>

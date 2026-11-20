@extends('shop.account.layout')
@php use App\Support\Money; @endphp

@section('title', 'My orders')

@section('account')
    <h1 class="h1 mb-8" data-split>My orders</h1>

    @if ($orders->isEmpty())
        <div class="border border-line p-8 text-center">
            <p class="text-mute mb-4">You haven’t placed any orders yet.</p>
            <a href="{{ route('shop.home') }}" class="btn-line max-w-xs mx-auto">Start shopping</a>
        </div>
    @else
        <ul class="border-t border-line">
            @foreach ($orders as $order)
                <li class="border-b border-line">
                    <a href="{{ route('account.orders.show', $order->number) }}" class="py-4 grid grid-cols-2 sm:grid-cols-4 gap-2 hover:bg-neutral-50 px-2 -mx-2">
                        <span><span class="label block">{{ $order->number }}</span><span class="text-mute">{{ $order->created_at->timezone(config('app.admin_timezone'))->format('j M Y') }}</span></span>
                        <span class="text-mute">{{ $order->itemCount() }} {{ \Illuminate\Support\Str::plural('item', $order->itemCount()) }}</span>
                        <span>{{ Money::format($order->total) }}</span>
                        <span class="label sm:text-right">{{ $order->statusLabel() }}</span>
                    </a>
                </li>
            @endforeach
        </ul>
        <div class="mt-6">{{ $orders->links() }}</div>
    @endif
@endsection

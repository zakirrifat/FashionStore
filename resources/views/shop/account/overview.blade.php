@extends('shop.account.layout')

@section('title', 'My account')

@section('account')
    <h1 class="text-2xl font-medium tracking-tight mb-8">Hello, {{ \Illuminate\Support\Str::before($user->name, ' ') }}</h1>

    <div class="grid sm:grid-cols-2 gap-4">
        <a href="{{ route('account.profile') }}" class="border border-line p-5 hover:border-ink transition block">
            <p class="label mb-3">My details</p>
            <p>{{ $user->name }}</p>
            <p class="text-mute">{{ $user->email }}</p>
        </a>
        <a href="{{ route('account.addresses') }}" class="border border-line p-5 hover:border-ink transition block">
            <p class="label mb-3">Default address</p>
            @if ($defaultAddress)
                <p class="text-mute">{{ implode(', ', array_slice($defaultAddress->lines(), 1, 4)) }}</p>
            @else
                <p class="text-mute">No address saved yet.</p>
            @endif
        </a>
        <a href="{{ route('wishlist') }}" class="border border-line p-5 hover:border-ink transition block">
            <p class="label mb-3">Wishlist</p>
            <p class="text-mute">{{ $wishlistCount }} {{ \Illuminate\Support\Str::plural('item', $wishlistCount) }} saved</p>
        </a>
        <a href="{{ route('account.orders') }}" class="border border-line p-5 hover:border-ink transition block">
            <p class="label mb-3">Orders</p>
            @if ($latestOrder)
                <p>Latest: {{ $latestOrder->number }}</p>
                <p class="text-mute">{{ $latestOrder->statusLabel() }} · {{ \App\Support\Money::format($latestOrder->total) }}</p>
            @else
                <p class="text-mute">No orders yet.</p>
            @endif
        </a>
    </div>
@endsection

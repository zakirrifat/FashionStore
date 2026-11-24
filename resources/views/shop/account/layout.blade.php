@extends('shop.layout')

@section('content')
    @php
        $links = config('shop.checkout_enabled')
            ? ['account.overview' => 'Overview', 'account.orders' => 'Orders', 'account.profile' => 'My details', 'account.addresses' => 'Addresses', 'wishlist' => 'Wishlist']
            : ['account.overview' => 'Overview', 'account.profile' => 'My details', 'wishlist' => 'Wishlist'];
    @endphp
    <div class="gutter container-x pt-12 md:pt-20 lg:grid lg:grid-cols-[220px_1fr] lg:gap-20">
        <nav aria-label="Account" class="mb-10 lg:mb-0" data-reveal>
            <p class="eyebrow text-mute mb-6 hidden lg:block">My account</p>
            <ul class="flex lg:block gap-6 overflow-x-auto lg:space-y-3 label whitespace-nowrap border-b border-line lg:border-0 pb-4 lg:pb-0 [scrollbar-width:none]">
                @foreach ($links as $route => $text)
                    <li class="shrink-0">
                        <a href="{{ route($route) }}" class="link-draw {{ request()->routeIs($route.'*') ? 'is-active' : 'text-mute hover:text-ink' }}">{{ $text }}</a>
                    </li>
                @endforeach
                <li class="shrink-0 lg:pt-6">
                    <form method="post" action="{{ route('logout') }}">
                        @csrf
                        <button class="text-mute hover:text-ink label link-draw">Sign out</button>
                    </form>
                </li>
            </ul>
        </nav>
        <section class="min-w-0">
            @include('shop.partials.status')
            @yield('account')
        </section>
    </div>
@endsection

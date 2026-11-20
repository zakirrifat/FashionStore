@extends('shop.layout')

@section('title', 'Sign in')

@section('content')
    <div class="gutter max-w-5xl mx-auto pt-16 md:pt-24 grid md:grid-cols-2 gap-12 md:gap-20">
        <section>
            <h1 class="h1 mb-8" data-split>Sign in</h1>
            @include('shop.partials.status')
            <form method="post" action="{{ route('login') }}" class="space-y-5">
                @csrf
                <x-shop.input name="email" label="Email" type="email" required autocomplete="email" autofocus />
                <x-shop.input name="password" label="Password" type="password" required autocomplete="current-password" />
                <div class="flex justify-between items-center">
                    <label class="flex items-center gap-3 cursor-pointer">
                        <input type="checkbox" name="remember" value="1" class="check"> Keep me signed in
                    </label>
                    <a href="{{ route('password.request') }}" class="link-u text-mute">Forgot password?</a>
                </div>
                <button class="btn-black">Sign in</button>
            </form>
        </section>
        <section class="md:border-l md:border-line md:pl-20">
            <h2 class="h2 mb-6">New customer</h2>
            <p class="text-mute mb-6">Create an account to save addresses, keep a wishlist across devices, and (soon) track your orders.</p>
            <a href="{{ route('register') }}" class="btn-line">Create account</a>
        </section>
    </div>
@endsection

@extends('shop.layout')

@section('title', 'Forgot password')

@section('content')
    <div class="gutter max-w-md mx-auto pt-16 md:pt-24">
        <h1 class="h1 mb-8" data-split>Forgot your password?</h1>
        <p class="text-mute mb-6">Enter your email and we’ll send you a link to choose a new one.</p>
        @include('shop.partials.status')
        <form method="post" action="{{ route('password.email') }}" class="space-y-5">
            @csrf
            <x-shop.input name="email" label="Email" type="email" required autocomplete="email" autofocus />
            <button class="btn-black">Send reset link</button>
            <p class="text-center"><a href="{{ route('login') }}" class="link-u text-mute">Back to sign in</a></p>
        </form>
    </div>
@endsection

@extends('shop.layout')

@section('title', 'Choose a new password')

@section('content')
    <div class="gutter max-w-md mx-auto pt-16 md:pt-24">
        <h1 class="h1 mb-8" data-split>Choose a new password</h1>
        <form method="post" action="{{ route('password.update') }}" class="space-y-5">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <x-shop.input name="email" label="Email" type="email" :value="$email" required autocomplete="email" />
            <x-shop.input name="password" label="New password" type="password" required autocomplete="new-password" autofocus />
            <p class="text-mute text-[12px] -mt-3">At least 10 characters, with letters and numbers.</p>
            <x-shop.input name="password_confirmation" label="Confirm new password" type="password" required autocomplete="new-password" />
            <button class="btn-black">Save new password</button>
        </form>
    </div>
@endsection

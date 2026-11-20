@extends('shop.layout')

@section('title', 'Create account')

@section('content')
    <div class="gutter max-w-md mx-auto pt-16 md:pt-24">
        <h1 class="h1 mb-8" data-split>Create account</h1>
        <form method="post" action="{{ route('register') }}" class="space-y-5">
            @csrf
            <x-shop.input name="name" label="Full name" required autocomplete="name" autofocus />
            <x-shop.input name="email" label="Email" type="email" required autocomplete="email" />
            <x-shop.input name="password" label="Password" type="password" required autocomplete="new-password" />
            <p class="text-mute text-[12px] -mt-3">At least 10 characters, with letters and numbers.</p>
            <x-shop.input name="password_confirmation" label="Confirm password" type="password" required autocomplete="new-password" />

            <label class="flex items-start gap-3 cursor-pointer">
                <input type="checkbox" name="marketing_opt_in" value="1" @checked(old('marketing_opt_in')) class="check mt-0.5">
                <span>Email me about new collections and events.</span>
            </label>
            <label class="flex items-start gap-3 cursor-pointer">
                <input type="checkbox" name="terms" value="1" @checked(old('terms')) class="check mt-0.5" required>
                <span>I accept the <a href="{{ route('shop.page', 'terms') }}" class="link-u" target="_blank">terms</a> and
                    <a href="{{ route('shop.page', 'privacy') }}" class="link-u" target="_blank">privacy policy</a>. *</span>
            </label>
            @error('terms')<p class="text-sale text-[12px]">{{ $message }}</p>@enderror

            <button class="btn-black">Create account</button>
            <p class="text-center text-mute">Already have an account? <a href="{{ route('login') }}" class="link-u text-ink">Sign in</a></p>
        </form>
    </div>
@endsection

@extends('shop.account.layout')

@section('title', 'My details')

@section('account')
    <div class="grid lg:grid-cols-2 gap-12">
        <form method="post" action="{{ route('account.profile.update') }}" class="space-y-5" x-data="{ email: @js(old('email', $user->email)) }">
            @csrf @method('PUT')
            <h1 class="h2" data-split>My details</h1>
            <x-shop.input name="name" label="Full name" :value="$user->name" required autocomplete="name" />
            <x-shop.input name="email" label="Email" type="email" :value="$user->email" required autocomplete="email" x-model="email" />
            <div x-show="email.toLowerCase() !== @js($user->email)" x-cloak>
                <x-shop.input name="current_password" label="Current password (to change email)" type="password" autocomplete="current-password" />
            </div>
            <x-shop.input name="phone" label="Phone" type="tel" :value="$user->phone" autocomplete="tel" />
            <label class="flex items-start gap-3 cursor-pointer">
                <input type="checkbox" name="marketing_opt_in" value="1" @checked(old('marketing_opt_in', $user->marketing_opt_in)) class="check mt-0.5">
                <span>Email me about new collections and events.</span>
            </label>
            <div class="max-w-xs"><button class="btn-black">Save details</button></div>
        </form>

        <form method="post" action="{{ route('account.password.update') }}" class="space-y-5">
            @csrf @method('PUT')
            <h2 class="h2">Change password</h2>
            <x-shop.input name="current_password" id="pw-current" label="Current password" type="password" bag="password" required autocomplete="current-password" />
            <x-shop.input name="password" id="pw-new" label="New password" type="password" bag="password" required autocomplete="new-password" />
            <p class="text-mute text-[12px] -mt-3">At least 10 characters, with letters and numbers.</p>
            <x-shop.input name="password_confirmation" id="pw-confirm" label="Confirm new password" type="password" bag="password" required autocomplete="new-password" />
            <div class="max-w-xs"><button class="btn-line">Change password</button></div>
        </form>
    </div>
@endsection

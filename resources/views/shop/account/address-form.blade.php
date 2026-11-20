@extends('shop.account.layout')

@php $editing = $address->exists; @endphp
@section('title', $editing ? 'Edit address' : 'Add address')

@section('account')
    <h1 class="h1 mb-8" data-split>{{ $editing ? 'Edit address' : 'Add address' }}</h1>

    <form method="post" action="{{ $editing ? route('account.addresses.update', $address) : route('account.addresses.store') }}"
          class="grid sm:grid-cols-2 gap-x-6 gap-y-5 max-w-2xl"
          x-data="{ country: @js(old('country', $address->country ?? 'ZA')) }">
        @csrf
        @if ($editing) @method('PUT') @endif

        <x-shop.input name="first_name" label="First name" :value="$address->first_name" required autocomplete="given-name" />
        <x-shop.input name="last_name" label="Last name" :value="$address->last_name" required autocomplete="family-name" />
        <div class="sm:col-span-2">
            <x-shop.input name="company" label="Company" :value="$address->company" autocomplete="organization" />
        </div>
        <div class="sm:col-span-2">
            <x-shop.input name="line1" label="Street address" :value="$address->line1" required autocomplete="address-line1" />
        </div>
        <div class="sm:col-span-2">
            <x-shop.input name="line2" label="Apartment, suite, complex (optional)" :value="$address->line2" autocomplete="address-line2" />
        </div>

        <label class="block">
            <span class="label">Country *</span>
            <select name="country" x-model="country" required autocomplete="country"
                    class="field-input mt-1">
                @foreach (\App\Models\Address::countries() as $code => $name)
                    <option value="{{ $code }}" @selected(old('country', $address->country) === $code)>{{ $name }}</option>
                @endforeach
            </select>
            @error('country')<span class="text-sale text-[12px]">{{ $message }}</span>@enderror
        </label>

        {{-- SA: province dropdown. Elsewhere: free text state/region. --}}
        <label class="block" x-show="country === 'ZA'">
            <span class="label">Province *</span>
            <select name="province" :disabled="country !== 'ZA'" autocomplete="address-level1"
                    class="field-input mt-1">
                <option value="">Choose…</option>
                @foreach (\App\Models\Address::ZA_PROVINCES as $p)
                    <option @selected(old('province', $address->province) === $p)>{{ $p }}</option>
                @endforeach
            </select>
            @error('province')<span class="text-sale text-[12px]">{{ $message }}</span>@enderror
        </label>
        <div x-show="country !== 'ZA'" x-cloak>
            <x-shop.input name="province" id="f-province-text" label="State / region" :value="$address->province"
                          autocomplete="address-level1" x-bind:disabled="country === 'ZA'" />
        </div>

        <x-shop.input name="city" label="City / town" :value="$address->city" required autocomplete="address-level2" />
        <x-shop.input name="postal_code" label="Postal code" :value="$address->postal_code" required autocomplete="postal-code" />
        <div class="sm:col-span-2">
            <x-shop.input name="phone" label="Phone (for the courier)" type="tel" :value="$address->phone" required autocomplete="tel" />
        </div>

        <label class="flex items-center gap-3 cursor-pointer sm:col-span-2">
            <input type="checkbox" name="is_default" value="1" @checked(old('is_default', $address->is_default)) class="check">
            Use as my default address
        </label>

        <div class="sm:col-span-2 flex gap-3 max-w-md">
            <button class="btn-black">{{ $editing ? 'Save address' : 'Add address' }}</button>
            <a href="{{ route('account.addresses') }}" class="btn-line">Cancel</a>
        </div>
    </form>
@endsection

@extends('shop.layout')
@php use App\Support\Money; use App\Models\Address; @endphp

@section('title', 'Checkout')
@section('minimal-chrome', '1')

@push('head')
    <meta name="robots" content="noindex">
@endpush

@section('content')
<div class="gutter container-x pt-10 md:pt-14 pb-24"
     x-data="checkout({
        quoteUrl: @js(route('checkout.quote')),
        country: @js(old('country', $defaults['country'])),
        method: @js((int) $selectedMethod),
        giftWrap: @js((bool) old('gift_wrap')),
        methods: @js($zoneMethods->map(fn ($m) => ['id' => $m->id, 'name' => $m->name, 'estimate' => $m->estimate, 'price' => Money::format($m->priceFor($totals->subtotal - $totals->discount)), 'free' => $m->priceFor($totals->subtotal - $totals->discount) === 0])),
        addresses: @js($savedAddresses->map(fn ($a) => $a->only(['id', 'first_name', 'last_name', 'company', 'line1', 'line2', 'city', 'province', 'postal_code', 'country', 'phone']))),
     })">

    <h1 class="h1 mb-3" data-split>Checkout</h1>
    {{-- Progress --}}
    <ol class="flex gap-6 eyebrow text-[10px] text-mute mb-12" data-reveal style="--d: 150ms">
        <li class="text-ink">1 Contact</li><li>2 Address</li><li>3 Delivery</li><li>4 Place order</li>
    </ol>

    @if ($errors->has('bag'))
        <div class="border border-sale text-sale px-5 py-4 mb-8" role="alert">
            @foreach ($errors->get('bag') as $msg)<p>{{ $msg }}</p>@endforeach
            <a href="{{ route('bag') }}" class="link-u">Go to your bag</a>
        </div>
    @endif
    @error('discount_code')
        <div class="border border-sale text-sale px-5 py-4 mb-8" role="alert">{{ $message }} <a href="{{ route('bag') }}" class="link-u">Change code</a></div>
    @enderror

    <form method="post" action="{{ route('checkout.place') }}" class="lg:grid lg:grid-cols-[1fr_420px] lg:gap-20" novalidate @submit="placing = true">
        @csrf
        <div class="space-y-14">
            <section data-reveal>
                <div class="flex items-baseline gap-4 mb-6"><span class="font-serif text-[28px] text-mute">01</span><h2 class="label">Contact</h2></div>
                @guest
                    <p class="text-mute mb-5">Have an account? <a href="{{ route('login') }}" class="link-u text-ink">Sign in</a> for saved addresses — or continue as a guest.</p>
                @endguest
                <div class="grid sm:grid-cols-2 gap-x-8 gap-y-6">
                    <x-shop.input name="email" label="Email" type="email" :value="$defaults['email']" required autocomplete="email" />
                    <x-shop.input name="phone" label="Phone" type="tel" :value="$defaults['phone']" required autocomplete="tel" />
                </div>
                <p class="text-mute text-[12px] mt-3">We’ll use these to arrange payment and delivery.</p>
            </section>

            <section data-reveal>
                <div class="flex items-baseline gap-4 mb-6"><span class="font-serif text-[28px] text-mute">02</span><h2 class="label">Delivery address</h2></div>
                @if ($savedAddresses->isNotEmpty())
                    <label class="field mb-6">
                        <select class="field-input" @change="useAddress($event.target.value)">
                            <option value="">Choose…</option>
                            @foreach ($savedAddresses as $a)
                                <option value="{{ $a->id }}">{{ implode(', ', array_slice($a->lines(), 0, 4)) }}</option>
                            @endforeach
                        </select>
                        <span class="field-label is-up">Use a saved address</span>
                    </label>
                @endif
                <div class="grid sm:grid-cols-2 gap-x-8 gap-y-6">
                    <x-shop.input name="first_name" label="First name" :value="$defaults['first_name']" required autocomplete="given-name" />
                    <x-shop.input name="last_name" label="Last name" :value="$defaults['last_name']" required autocomplete="family-name" />
                    <div class="sm:col-span-2"><x-shop.input name="company" label="Company (optional)" :value="$defaults['company']" autocomplete="organization" /></div>
                    <div class="sm:col-span-2"><x-shop.input name="line1" label="Street address" :value="$defaults['line1']" required autocomplete="address-line1" /></div>
                    <div class="sm:col-span-2"><x-shop.input name="line2" label="Apartment, suite, complex (optional)" :value="$defaults['line2']" autocomplete="address-line2" /></div>

                    <label class="field">
                        <select name="country" x-model="country" @change="refresh()" required autocomplete="country" class="field-input">
                            @foreach (Address::countries() as $code => $name)
                                <option value="{{ $code }}" @selected(old('country', $defaults['country']) === $code)>{{ $name }}</option>
                            @endforeach
                        </select>
                        <span class="field-label is-up">Country *</span>
                        @error('country')<span class="field-error">{{ $message }}</span>@enderror
                    </label>
                    <label class="field" x-show="country === 'ZA'">
                        <select name="province" :disabled="country !== 'ZA'" autocomplete="address-level1" class="field-input" @error('province') aria-invalid="true" @enderror>
                            <option value="">Choose…</option>
                            @foreach (Address::ZA_PROVINCES as $p)
                                <option @selected(old('province', $defaults['province']) === $p)>{{ $p }}</option>
                            @endforeach
                        </select>
                        <span class="field-label is-up">Province *</span>
                        @error('province')<span class="field-error">{{ $message }}</span>@enderror
                    </label>
                    <div x-show="country !== 'ZA'" x-cloak>
                        <x-shop.input name="province" id="f-province-text" label="State / region" :value="$defaults['province']"
                                      autocomplete="address-level1" x-bind:disabled="country === 'ZA'" />
                    </div>
                    <x-shop.input name="city" label="City / town" :value="$defaults['city']" required autocomplete="address-level2" />
                    <x-shop.input name="postal_code" label="Postal code" :value="$defaults['postal_code']" required autocomplete="postal-code" />
                </div>
                @auth
                    <label class="flex items-center gap-3 mt-6 cursor-pointer">
                        <input type="checkbox" name="save_address" value="1" @checked(old('save_address', true)) class="check"> Save this address to my account
                    </label>
                @endauth
            </section>

            <section data-reveal>
                <div class="flex items-baseline gap-4 mb-6"><span class="font-serif text-[28px] text-mute">03</span><h2 class="label">Delivery</h2></div>
                <template x-if="methods.length === 0">
                    <p class="border border-line p-5 text-mute">Sorry, we don’t deliver to this country yet. Please <a href="{{ route('shop.contact') }}" class="link-u text-ink">contact us</a>.</p>
                </template>
                <div class="space-y-2">
                    <template x-for="m in methods" :key="m.id">
                        <label class="flex items-center gap-4 border px-5 py-4 cursor-pointer transition-colors duration-300" :class="method === m.id ? 'border-ink bg-paper-2/50' : 'border-line hover:border-[#c9c5bb]'">
                            <input type="radio" name="shipping_method_id" :value="m.id" x-model.number="method" @change="refresh()" class="radio">
                            <span class="flex-1"><span x-text="m.name"></span> <span class="text-mute text-[13px] block sm:inline" x-text="m.estimate ? m.estimate : ''"></span></span>
                            <span x-text="m.free ? 'Free' : m.price"></span>
                        </label>
                    </template>
                </div>
                @error('shipping_method_id')<p class="field-error mt-2">{{ $message }}</p>@enderror
            </section>

            <section data-reveal>
                <div class="flex items-baseline gap-4 mb-6"><span class="font-serif text-[28px] text-mute">04</span><h2 class="label">Gift &amp; notes</h2></div>
                @php $wrap = (int) ($settings['gift_wrap_price'] ?? 0); @endphp
                <label class="flex items-center gap-3 cursor-pointer">
                    <input type="checkbox" name="gift_wrap" value="1" x-model="giftWrap" @change="refresh()" class="check">
                    Gift wrap this order <span class="text-mute">({{ $wrap ? Money::format($wrap) : 'complimentary' }})</span>
                </label>
                <div x-show="giftWrap" x-collapse x-cloak>
                    <label class="field mt-4">
                        <textarea name="gift_message" rows="2" maxlength="300" placeholder=" " class="field-input resize-none">{{ old('gift_message') }}</textarea>
                        <span class="field-label">Gift message (optional)</span>
                    </label>
                </div>
                <label class="field mt-6">
                    <textarea name="customer_note" rows="2" maxlength="1000" placeholder=" " class="field-input resize-none">{{ old('customer_note') }}</textarea>
                    <span class="field-label">Note for us (optional)</span>
                </label>
            </section>
        </div>

        {{-- Summary --}}
        <aside class="mt-14 lg:mt-0" data-reveal style="--d: 150ms">
            <div class="lg:sticky lg:top-8 bg-paper-2/60 p-6 md:p-8">
                <h2 class="label mb-6">Order summary</h2>
                <div x-ref="summary" :class="loading && 'opacity-40'" class="transition-opacity duration-300">
                    @include('shop.partials.checkout-summary', ['totals' => $totals])
                </div>
                <div class="border-t border-line mt-6 pt-6 space-y-5">
                    <p class="text-[13px] leading-relaxed"><span class="label">Payment</span><br>Bank transfer. After you place your order we’ll contact you with payment details. Your items are reserved for you.</p>
                    <label class="flex items-start gap-3 cursor-pointer">
                        <input type="checkbox" name="terms" value="1" @checked(old('terms')) class="check mt-0.5">
                        <span class="text-[13px]">I accept the <a href="{{ route('shop.page', 'terms') }}" target="_blank" class="link-u">terms</a> and
                            <a href="{{ route('shop.page', 'privacy') }}" target="_blank" class="link-u">privacy policy</a>.</span>
                    </label>
                    @error('terms')<p class="field-error">{{ $message }}</p>@enderror
                    <button class="btn-black h-14" :disabled="placing || methods.length === 0">
                        <span x-show="!placing">Place order</span>
                        <span x-show="placing" x-cloak class="inline-flex items-center gap-2">
                            <svg class="w-4 h-4 animate-spin" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-opacity=".25" stroke-width="2"/><path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="2"/></svg>
                            Placing order…
                        </span>
                    </button>
                    <p class="text-mute text-[12px] flex items-center gap-2">
                        <svg class="w-3.5 h-3.5" viewBox="0 0 16 16" fill="none" stroke="currentColor"><rect x="3" y="7" width="10" height="7" rx="1"/><path d="M5 7V5a3 3 0 0 1 6 0v2"/></svg>
                        Your details are sent securely.
                    </p>
                </div>
            </div>
        </aside>
    </form>
</div>
@endsection

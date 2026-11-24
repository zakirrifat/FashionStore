@extends('shop.layout')

@section('title', 'Contact us')

@section('content')
    <div class="gutter max-w-6xl mx-auto pt-12 md:pt-16 grid md:grid-cols-[1fr_2fr] gap-12">
        <aside>
            <h1 class="h1 mb-8" data-split>Contact us</h1>
            <dl class="space-y-5">
                @if ($settings['contact_email'])
                    <div>
                        <dt class="label text-mute mb-1">Email</dt>
                        <dd><a href="mailto:{{ $settings['contact_email'] }}" class="link-u">{{ $settings['contact_email'] }}</a></dd>
                    </div>
                @endif
                @if ($settings['contact_phone'])
                    <div>
                        <dt class="label text-mute mb-1">Phone</dt>
                        <dd><a href="tel:{{ preg_replace('/[^0-9+]/', '', $settings['contact_phone']) }}" class="link-u">{{ $settings['contact_phone'] }}</a></dd>
                    </div>
                @endif
                @if ($settings['contact_hours'])
                    <div>
                        <dt class="label text-mute mb-1">Hours</dt>
                        <dd>{{ $settings['contact_hours'] }}</dd>
                    </div>
                @endif
                @if ($settings['contact_address'])
                    <div>
                        <dt class="label text-mute mb-1">Address</dt>
                        <dd class="whitespace-pre-line">{{ $settings['contact_address'] }}</dd>
                    </div>
                @endif
                <div>
                    <dt class="label text-mute mb-1">Help</dt>
                    <dd><a href="{{ route('shop.faq') }}" class="link-u">Read our FAQs</a></dd>
                </div>
            </dl>
        </aside>

        <section>
            @if (session('sent'))
                <div class="border border-ink p-6" role="status">
                    <p class="label mb-2">Thank you</p>
                    <p>We’ve received your message and will reply by email as soon as possible.</p>
                </div>
            @else
                <form method="post" action="{{ route('shop.contact.send') }}" class="grid sm:grid-cols-2 gap-x-6 gap-y-5" novalidate>
                    @csrf
                    {{-- Hidden from people; bots fill it in. --}}
                    <div class="hidden" aria-hidden="true">
                        <input type="text" name="website" tabindex="-1" autocomplete="off">
                    </div>

                    @php
                        $field = 'field-input mt-1';
                    @endphp

                    <label class="block">
                        <span class="label">Name *</span>
                        <input name="name" value="{{ old('name') }}" required maxlength="100" autocomplete="name" class="{{ $field }}">
                        @error('name')<span class="text-sale text-[12px]">{{ $message }}</span>@enderror
                    </label>
                    <label class="block">
                        <span class="label">Email *</span>
                        <input type="email" name="email" value="{{ old('email') }}" required autocomplete="email" class="{{ $field }}">
                        @error('email')<span class="text-sale text-[12px]">{{ $message }}</span>@enderror
                    </label>
                    <label class="block">
                        <span class="label">Phone</span>
                        <input type="tel" name="phone" value="{{ old('phone') }}" autocomplete="tel" class="{{ $field }}">
                    </label>
                    <label class="block">
                        <span class="label">Order number</span>
                        <input name="order_number" value="{{ old('order_number') }}" class="{{ $field }}">
                    </label>
                    <label class="block sm:col-span-2">
                        <span class="label">Subject *</span>
                        <select name="subject" required class="{{ $field }}">
                            <option value="">Choose a subject</option>
                            @foreach ($subjects as $s)
                                <option value="{{ $s }}" @selected(old('subject') === $s)>{{ $s }}</option>
                            @endforeach
                        </select>
                        @error('subject')<span class="text-sale text-[12px]">{{ $message }}</span>@enderror
                    </label>
                    <label class="block sm:col-span-2">
                        <span class="label">Message *</span>
                        <textarea name="message" rows="6" required minlength="10" maxlength="5000" class="{{ $field }} resize-y">{{ old('message') }}</textarea>
                        @error('message')<span class="text-sale text-[12px]">{{ $message }}</span>@enderror
                    </label>
                    <div class="sm:col-span-2 max-w-xs">
                        <button class="btn-black">Send message</button>
                    </div>
                </form>
            @endif
        </section>
    </div>
@endsection

@extends('shop.layout')

@section('title', 'Contact us')

@section('content')
    <div class="gutter max-w-6xl mx-auto pt-12 md:pt-16 grid md:grid-cols-[5fr_7fr] gap-6 md:gap-8">
        @php
            $contactBg = $settings['contact_background'] ?? null;
            // Tint = the page colour laid over the photo so the text stays readable (full class names, for Tailwind).
            $contactTint = ['light' => 'opacity-55', 'medium' => 'opacity-75', 'strong' => 'opacity-90'][$settings['contact_background_tint'] ?? 'medium'] ?? 'opacity-75';
        @endphp
        <aside @class(['relative isolate overflow-hidden px-6 py-8 md:px-10 md:py-9', 'bg-paper-2/60' => blank($contactBg)])>
            @if (filled($contactBg))
                {{-- Background image chosen in Admin › Store settings › Contact details --}}
                <img src="{{ \App\Support\Img::url($contactBg, 800) }}"
                     srcset="{{ \App\Support\Img::srcset($contactBg, [400, 800, 1200, 1600]) }}"
                     sizes="(min-width: 768px) 40vw, 100vw" alt="" aria-hidden="true" decoding="async"
                     class="absolute inset-0 -z-20 w-full h-full object-cover">
                <div class="absolute inset-0 -z-10 bg-paper-2 {{ $contactTint }}" aria-hidden="true"></div>
            @endif
            @include('shop.partials.hanger-mark', ['align' => 'start'])
            <h1 class="h1 mb-6 md:text-[clamp(2rem,3.6vw,3.25rem)] whitespace-nowrap" data-split>Contact us</h1>
            <dl class="space-y-4">
                @if ($settings['contact_email'])
                    <div>
                        <dt class="label text-mute mb-0.5">Email</dt>
                        {{-- Same button look as WhatsApp below; a small copy icon sits in its top-right corner.
                             (The icon is laid over the link, not inside it: a button can't go inside a link.) --}}
                        <dd class="group/email relative flex w-full max-w-[22rem] mt-1"
                            x-data="{
                                copied: false,
                                async copy(text) {
                                    try {
                                        await navigator.clipboard.writeText(text);
                                    } catch (e) {
                                        // No clipboard API (e.g. plain http): copy through a hidden text box instead.
                                        const box = Object.assign(document.createElement('textarea'), { value: text });
                                        box.setAttribute('readonly', '');
                                        box.style.cssText = 'position:fixed;opacity:0';
                                        document.body.appendChild(box);
                                        box.select();
                                        document.execCommand('copy');
                                        box.remove();
                                    }
                                    this.copied = true;
                                    clearTimeout(this.t);
                                    this.t = setTimeout(() => this.copied = false, 2000);
                                },
                            }">
                            <a href="mailto:{{ $settings['contact_email'] }}"
                               class="group btn-dark-outline w-full min-w-0 px-10 normal-case tracking-normal text-[13px] before:bg-baby hover:text-night hover:border-baby group-hover/email:text-night group-hover/email:border-baby group-hover/email:before:translate-y-0"
                               aria-label="Email us at {{ $settings['contact_email'] }}">
                                <svg class="w-[18px] h-[18px] shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <rect x="3" y="5" width="18" height="14" rx="1.5"/>
                                    <path d="M3.5 6l8.5 7 8.5-7"/>
                                </svg>
                                <span class="truncate">{{ $settings['contact_email'] }}</span>
                            </a>
                            <button type="button" @click="copy(@js($settings['contact_email']))"
                                    class="absolute top-1 right-1 grid place-items-center w-7 h-7 text-ink group-hover/email:text-night opacity-70 hover:opacity-100 focus-visible:opacity-100 transition-[opacity,transform,color] duration-300 active:scale-90"
                                    :aria-label="copied ? 'Email address copied' : 'Copy email address'" title="Copy email address">
                                <svg x-show="!copied" class="w-[15px] h-[15px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <rect x="8.5" y="8.5" width="12" height="12" rx="1.5"/>
                                    <path d="M15.5 8.5V5a1.5 1.5 0 0 0-1.5-1.5H5A1.5 1.5 0 0 0 3.5 5v9A1.5 1.5 0 0 0 5 15.5h3.5"/>
                                </svg>
                                <svg x-show="copied" x-cloak class="w-[15px] h-[15px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M5 12.5l4.5 4.5L19 7.5"/>
                                </svg>
                            </button>
                            <span class="sr-only" role="status" x-text="copied ? 'Email address copied' : ''"></span>
                            <span x-show="copied" x-cloak x-transition.opacity class="absolute -top-5 right-0 label text-mute whitespace-nowrap pointer-events-none" aria-hidden="true">Copied</span>
                        </dd>
                    </div>
                @endif
                @php $whatsapp = preg_replace('/\D/', '', (string) ($settings['contact_whatsapp'] ?? '')); @endphp
                @if (strlen($whatsapp) >= 7)
                    <div>
                        <dt class="label text-mute mb-0.5">WhatsApp</dt>
                        <dd>
                            <a href="https://wa.me/{{ $whatsapp }}?text={{ rawurlencode('Hi '.config('app.name').', I have a question.') }}"
                               target="_blank" rel="noopener" class="group btn-dark-outline w-full max-w-[22rem] mt-1 before:bg-baby hover:text-night hover:border-baby"
                               aria-label="Chat with us on WhatsApp (opens WhatsApp)">
                                <svg class="w-[18px] h-[18px] shrink-0 transition-colors duration-300 group-hover:text-[#25D366]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M3.5 20.5l1.3-4A8.5 8.5 0 1 1 8 19.6z"/>
                                    <path d="M9.2 8.3c.2-.4.5-.5.8-.5h.4c.2 0 .4.1.5.4l.6 1.4c.1.2 0 .5-.1.6l-.5.6c-.1.1-.1.3 0 .5.6 1 1.4 1.8 2.4 2.4.2.1.4.1.5 0l.6-.5c.2-.1.4-.2.6-.1l1.4.6c.3.1.4.3.4.5v.4c0 .3-.1.6-.5.8-.6.4-1.4.5-2.1.3-2.2-.7-4-2.5-4.7-4.7-.2-.7-.1-1.5.3-2.1z"/>
                                </svg>
                                <span>Chat on WhatsApp</span>
                            </a>
                        </dd>
                    </div>
                @endif
                @if ($settings['contact_phone'])
                    <div>
                        <dt class="label text-mute mb-0.5">Phone</dt>
                        <dd><a href="tel:{{ preg_replace('/[^0-9+]/', '', $settings['contact_phone']) }}" class="link-u">{{ $settings['contact_phone'] }}</a></dd>
                    </div>
                @endif
                @if ($settings['contact_hours'])
                    <div>
                        <dt class="label text-mute mb-0.5">Hours</dt>
                        <dd>{{ $settings['contact_hours'] }}</dd>
                    </div>
                @endif
                @if ($settings['contact_address'])
                    <div>
                        <dt class="label text-mute mb-0.5">Address</dt>
                        <dd class="whitespace-pre-line">{{ $settings['contact_address'] }}</dd>
                    </div>
                @endif
                <div>
                    <dt class="label text-mute mb-0.5">Help</dt>
                    <dd><a href="{{ route('shop.faq') }}" class="link-u">Read our FAQs</a></dd>
                </div>
            </dl>
        </aside>

        <section>
            @if (session('sent'))
                @php $sent = is_array(session('sent')) ? session('sent') : []; @endphp
                <div class="bg-paper-2/60 px-6 py-10 md:px-12 md:py-14" role="status">
                    <svg class="draw-check w-14 h-14 mb-8" viewBox="0 0 52 52" fill="none" stroke="currentColor" stroke-width="1.2" aria-hidden="true">
                        <circle cx="26" cy="26" r="25"/><path d="M15 27l7 7 15-16"/>
                    </svg>
                    <p class="eyebrow text-mute mb-4" data-reveal>Message sent</p>
                    <h2 class="h2" data-split data-delay="200">
                        Thank you{{ filled($sent['name'] ?? null) ? ', '.$sent['name'] : '' }}. We’ll be in touch soon.
                    </h2>
                    <p class="lead text-mute mt-5 max-w-xl" data-reveal style="--d: 450ms">
                        Your message has reached our team.
                        @if (filled($sent['phone'] ?? null))
                            We’ll get back to you on <span class="text-ink whitespace-nowrap">{{ $sent['phone'] }}</span>{!! filled($sent['email'] ?? null) ? ' or by email at <span class="text-ink">'.e($sent['email']).'</span>' : '' !!}.
                        @else
                            We’ll get back to you as soon as possible.
                        @endif
                    </p>

                    <div class="mt-10 flex flex-col sm:flex-row gap-3 sm:max-w-md" data-reveal style="--d: 650ms">
                        <a href="{{ route('shop.home') }}" class="btn-black">Continue shopping</a>
                        <a href="{{ route('shop.faq') }}" class="btn-line">Browse FAQs</a>
                    </div>
                    <p class="mt-6 text-[13px] text-mute" data-reveal style="--d: 700ms">
                        Forgot something? <a href="{{ route('shop.contact') }}" class="link-u text-ink">Send another message</a>
                    </p>
                </div>
            @else
                <form method="post" action="{{ route('shop.contact.send') }}" novalidate
                      class="bg-paper-2/60 px-6 py-8 md:px-10 md:py-9 grid sm:grid-cols-2 gap-x-5 gap-y-4"
                      x-data="{ sending: false }" @submit="if (sending) { $event.preventDefault() } else { sending = true }"
                      @pageshow.window="sending = false" :aria-busy="sending">
                    @csrf
                    {{-- Hidden from people; bots fill it in. --}}
                    <div class="hidden" aria-hidden="true">
                        <input type="text" name="website" tabindex="-1" autocomplete="off">
                    </div>

                    <div class="sm:col-span-2 -mb-1">
                        <h2 class="h3">Send us a message</h2>
                        <p class="text-[13px] text-mute mt-1">Fields marked * are required.</p>
                    </div>

                    @php
                        $optional = '<span class="text-mute normal-case tracking-normal font-normal">(optional)</span>';
                    @endphp

                    <label class="block">
                        <span class="label">Name *</span>
                        <input name="name" value="{{ old('name') }}" required maxlength="100" autocomplete="name"
                               placeholder="Your full name" class="field-box" @error('name') aria-invalid="true" @enderror>
                        @error('name')<span class="field-error">{{ $message }}</span>@enderror
                    </label>
                    <label class="block">
                        <span class="label">Phone *</span>
                        <input type="tel" name="phone" value="{{ old('phone') }}" required autocomplete="tel" inputmode="tel"
                               placeholder="+27 82 123 4567" class="field-box" @error('phone') aria-invalid="true" @enderror>
                        @error('phone')<span class="field-error">{{ $message }}</span>@else<span class="mt-1 block text-[12px] text-mute">With country code, so we can call or WhatsApp you.</span>@enderror
                    </label>
                    <label class="block">
                        <span class="label">Email {!! $optional !!}</span>
                        <input type="email" name="email" value="{{ old('email') }}" autocomplete="email"
                               placeholder="you@example.com" class="field-box" @error('email') aria-invalid="true" @enderror>
                        @error('email')<span class="field-error">{{ $message }}</span>@enderror
                    </label>
                    <label class="block">
                        <span class="label">Order number {!! $optional !!}</span>
                        <input name="order_number" value="{{ old('order_number') }}"
                               placeholder="If it's about an order" class="field-box">
                    </label>
                    <label class="block sm:col-span-2">
                        <span class="label">Subject *</span>
                        <select name="subject" required class="field-box" @error('subject') aria-invalid="true" @enderror>
                            <option value="" disabled @selected(! old('subject'))>Choose a subject</option>
                            @foreach ($subjects as $s)
                                <option value="{{ $s }}" @selected(old('subject') === $s)>{{ $s }}</option>
                            @endforeach
                        </select>
                        @error('subject')<span class="field-error">{{ $message }}</span>@enderror
                    </label>
                    <label class="block sm:col-span-2">
                        <span class="label">Message *</span>
                        <textarea name="message" rows="4" required minlength="10" maxlength="5000"
                                  placeholder="How can we help?" class="field-box resize-y" @error('message') aria-invalid="true" @enderror>{{ old('message') }}</textarea>
                        @error('message')<span class="field-error">{{ $message }}</span>@enderror
                    </label>
                    <div class="sm:col-span-2 pt-1">
                        <div class="sm:w-60">
                            <button class="btn-black" x-show="!sending">Send message</button>
                            <div x-show="sending" x-cloak class="send-progress" role="status">
                                <span class="send-progress-label">
                                    Sending your message<span class="send-dots" aria-hidden="true"><i>.</i><i>.</i><i>.</i></span>
                                </span>
                                <span class="send-progress-bar" aria-hidden="true"></span>
                            </div>
                        </div>
                    </div>
                </form>
            @endif
        </section>
    </div>
@endsection

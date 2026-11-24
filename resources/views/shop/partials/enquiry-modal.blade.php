{{-- ───────── "Ask for price" popup (every page). Opened with $store.enquiry.open({...}) ───────── --}}
<div x-data x-show="$store.enquiry.isOpen" x-cloak class="fixed inset-0 z-[92]" role="dialog" aria-modal="true" aria-labelledby="enquiry-title"
     @keydown.escape.window="$store.enquiry.close()"
     x-effect="document.documentElement.style.overflow = $store.enquiry.isOpen ? 'hidden' : ''">
    <div class="scrim" @click="$store.enquiry.close()" x-show="$store.enquiry.isOpen" x-transition.opacity.duration.400ms></div>

    {{-- z-[70]: above the .scrim (z-60), like .drawer --}}
    <div class="absolute z-[70] inset-x-0 bottom-0 sm:inset-0 sm:m-auto sm:h-fit sm:max-w-md bg-paper max-h-[92dvh] overflow-y-auto shadow-2xl"
         data-lenis-prevent
         x-show="$store.enquiry.isOpen"
         x-transition:enter="transition duration-500 ease-[var(--ease-out-quint)]" x-transition:enter-start="translate-y-8 opacity-0"
         x-transition:leave="transition duration-200 ease-in" x-transition:leave-end="translate-y-4 opacity-0"
         x-init="$watch('$store.enquiry.isOpen', (o) => o && setTimeout(() => $el.querySelector('input[name=name]')?.focus({ preventScroll: true }), 300))">
        <div class="flex items-center justify-between px-6 h-14 border-b border-line">
            <span id="enquiry-title" class="label">Ask for price</span>
            <button type="button" class="label link-draw" @click="$store.enquiry.close()">Close</button>
        </div>

        {{-- Product being asked about --}}
        <template x-if="$store.enquiry.product">
            <div class="flex gap-4 px-6 pt-6">
                <template x-if="$store.enquiry.product.image">
                    <img :src="$store.enquiry.product.image" alt="" class="w-16 aspect-[3/4] object-cover shrink-0">
                </template>
                <div class="min-w-0">
                    <p class="text-[14px] leading-snug" x-text="$store.enquiry.product.name"></p>
                    <p class="text-[12px] text-mute mt-1"
                       x-text="[$store.enquiry.product.colour, $store.enquiry.product.size ? 'Size ' + $store.enquiry.product.size : null].filter(Boolean).join(' · ')"></p>
                </div>
            </div>
        </template>

        {{-- Sent --}}
        <div x-show="$store.enquiry.sent" class="px-6 py-8" role="status">
            <p class="label mb-2">Request sent</p>
            <p class="text-ink-2" x-text="$store.enquiry.thanks"></p>
            <button type="button" class="btn-black mt-6" @click="$store.enquiry.close()">Continue browsing</button>
        </div>

        {{-- Form --}}
        <form x-show="!$store.enquiry.sent" class="px-6 pt-5 pb-6 space-y-4" novalidate
              @submit.prevent="$store.enquiry.submit()">
            <p class="text-[13px] text-mute">Leave your details and we’ll get back to you with the price.</p>

            {{-- Hidden from people; bots fill it in. --}}
            <div class="hidden" aria-hidden="true">
                <input type="text" name="website" tabindex="-1" autocomplete="off" x-model="$store.enquiry.form.website">
            </div>

            <label class="block">
                <span class="label">Name *</span>
                <input name="name" required maxlength="100" autocomplete="name" class="field-input mt-1"
                       x-model="$store.enquiry.form.name" :aria-invalid="!!$store.enquiry.errors.name">
                <span class="block text-[12px] text-sale mt-1" x-show="$store.enquiry.errors.name" x-text="$store.enquiry.errors.name"></span>
            </label>

            <label class="block">
                <span class="label">Phone *</span>
                <input type="tel" name="phone" required maxlength="30" autocomplete="tel" inputmode="tel" class="field-input mt-1"
                       x-model="$store.enquiry.form.phone" :aria-invalid="!!$store.enquiry.errors.phone">
                <span class="block text-[12px] text-sale mt-1" x-show="$store.enquiry.errors.phone" x-text="$store.enquiry.errors.phone"></span>
            </label>

            <label class="block">
                <span class="label">Email <span class="text-mute normal-case tracking-normal font-normal">(optional)</span></span>
                <input type="email" name="email" maxlength="150" autocomplete="email" class="field-input mt-1"
                       x-model="$store.enquiry.form.email" :aria-invalid="!!$store.enquiry.errors.email">
                <span class="block text-[12px] text-sale mt-1" x-show="$store.enquiry.errors.email" x-text="$store.enquiry.errors.email"></span>
            </label>

            <label class="block">
                <span class="label">Message <span class="text-mute normal-case tracking-normal font-normal">(optional)</span></span>
                <textarea name="message" rows="3" maxlength="2000" class="field-input mt-1 resize-y"
                          placeholder="e.g. Do you have it in another colour?"
                          x-model="$store.enquiry.form.message"></textarea>
                <span class="block text-[12px] text-sale mt-1" x-show="$store.enquiry.errors.message" x-text="$store.enquiry.errors.message"></span>
            </label>

            <p class="text-[12px] text-sale" x-show="$store.enquiry.errors.form" x-text="$store.enquiry.errors.form" role="alert"></p>

            <button type="submit" class="btn-black" :disabled="$store.enquiry.sending">
                <span x-text="$store.enquiry.sending ? 'Sending…' : 'Send request'">Send request</span>
            </button>
        </form>
    </div>
</div>

@extends('shop.layout')

@section('title', 'FAQs')

@section('content')
    <div class="gutter max-w-4xl mx-auto pt-12 md:pt-16">
        <p class="eyebrow text-mute mb-5" data-reveal>Help</p>
        <h1 class="h1 mb-6" data-split>Frequently asked questions</h1>
        <p class="lead text-mute mb-12" data-reveal style="--d: 200ms">Can’t find your answer? <a href="{{ route('shop.contact') }}" class="link-u text-ink">Contact us</a>.</p>

        @forelse ($faqs as $topic => $items)
            <section class="mb-14 md:grid md:grid-cols-[220px_1fr] md:gap-10" data-reveal>
                <h2 class="eyebrow text-mute mb-4 md:pt-5">{{ $topic }}</h2>
                <div class="border-t border-line">
                    @foreach ($items as $faq)
                        <div class="border-b border-line" x-data="{ o: false }">
                            <button type="button" @click="o = !o" :aria-expanded="o" class="w-full py-5 flex justify-between items-start gap-6 text-left text-[16px]">
                                <span>{{ $faq->question }}</span>
                                <span class="mt-1 transition-transform duration-500 ease-[var(--ease-out-quint)]" :class="o && 'rotate-45'" aria-hidden="true">+</span>
                            </button>
                            <div x-show="o" x-collapse.duration.500ms x-cloak>
                                <div class="rich pb-6 text-mute">{!! $faq->answerHtml() !!}</div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>
        @empty
            <p class="text-mute">No questions yet.</p>
        @endforelse
    </div>
@endsection

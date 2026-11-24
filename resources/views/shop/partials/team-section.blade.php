{{-- "Meet Our Leadership" under the About page. Phones: swipe row. Tablets and up: centred rows. --}}
@php
    use App\Support\Img;

    $shape = $team['shape'];
    $frame = match ($shape) {
        'circle' => 'aspect-square rounded-full w-3/4 mx-auto',
        'square' => 'aspect-square rounded-2xl',
        default => 'aspect-[4/5] rounded-2xl',
    };
    $centred = $shape === 'circle';
@endphp

<section class="section-mt pb-4" aria-labelledby="team-heading">
    <header class="gutter max-w-3xl mx-auto text-center mb-10 md:mb-14">
        <p class="eyebrow text-mute mb-4" data-reveal>{{ config('app.name') }}</p>
        <h2 id="team-heading" class="section-title" data-reveal style="--d: 100ms">{{ $team['heading'] }}</h2>
        @if (filled($team['subtitle']))
            <p class="mt-4 text-[15px] md:text-[17px] leading-relaxed text-mute" data-reveal style="--d: 200ms">{{ $team['subtitle'] }}</p>
        @endif
    </header>

    <ul class="edge flex gap-4 md:gap-6 overflow-x-auto snap-x snap-mandatory overscroll-x-contain [scrollbar-width:none] [&::-webkit-scrollbar]:hidden
               md:flex-wrap md:justify-center md:overflow-visible md:snap-none max-w-[1440px] mx-auto" data-stagger="80">
        @foreach ($team['members'] as $member)
            @php
                $name = $member['name'];
                $initials = collect(preg_split('/\s+/', trim($name)))->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('');
                $link = filled($member['link'] ?? null) && preg_match('#^https?://#i', $member['link']) ? $member['link'] : null;
                $linkLabel = $link && str_contains(strtolower($link), 'linkedin.com') ? 'LinkedIn' : 'Profile';
            @endphp
            <li class="snap-start shrink-0 w-[72vw] sm:w-[44vw] md:w-[calc((100%-3rem)/3)] lg:w-[calc((100%-4.5rem)/4)] max-w-[340px] {{ $centred ? 'text-center' : '' }}">
                <div class="relative overflow-hidden bg-paper-2 {{ $frame }}">
                    @if (filled($member['photo'] ?? null))
                        <img src="{{ Img::url($member['photo'], 800) }}" srcset="{{ Img::srcset($member['photo'], [400, 800, 1200]) }}"
                             sizes="(min-width: 1024px) 340px, (min-width: 640px) 44vw, 72vw"
                             alt="{{ $name }}" loading="lazy" class="absolute inset-0 w-full h-full object-cover object-top">
                    @else
                        <span class="absolute inset-0 grid place-items-center section-title text-mute" aria-hidden="true">{{ $initials }}</span>
                    @endif
                </div>

                <div class="mt-5">
                    @if (filled($member['tag'] ?? null))
                        <p class="label text-mute mb-2">{{ sprintf('%02d', $loop->iteration) }} / {{ $member['tag'] }}</p>
                    @endif
                    <h3 class="text-[17px] md:text-[19px] font-medium text-ink">{{ $name }}</h3>
                    <p class="mt-1 text-[15px] text-ink-2">{{ $member['designation'] ?? '' }}</p>
                    @if (filled($member['bio'] ?? null))
                        <p class="mt-3 text-[14px] leading-relaxed text-mute">{{ $member['bio'] }}</p>
                    @endif
                    @if ($link)
                        <a href="{{ $link }}" target="_blank" rel="noopener noreferrer" class="inline-block label link-u mt-4">
                            {{ $linkLabel }}<span class="sr-only"> — {{ $name }} (opens in a new tab)</span>
                        </a>
                    @endif
                </div>
            </li>
        @endforeach
    </ul>
</section>

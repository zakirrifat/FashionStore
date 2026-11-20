@extends(auth()->check() ? 'shop.account.layout' : 'shop.layout')

@section('title', 'Wishlist')

@section(auth()->check() ? 'account' : 'content')
    <div class="{{ auth()->check() ? '' : 'gutter pt-10' }}">
        <div class="flex justify-between items-end mb-6">
            <h1 class="h2" data-split>Wishlist <span class="text-mute">({{ $items->count() }})</span></h1>
        </div>

        @guest
            @if ($items->isNotEmpty())
                <p class="border border-line px-4 py-3 mb-6 text-mute">
                    Your wishlist is saved on this device only.
                    <a href="{{ route('login') }}" class="link-u text-ink">Sign in</a> or
                    <a href="{{ route('register') }}" class="link-u text-ink">create an account</a> to keep it.
                </p>
            @endif
        @endguest

        @if ($items->isEmpty())
            <div class="py-20 text-center">
                <p class="label mb-2">Your wishlist is empty</p>
                <p class="text-mute mb-6">Tap “Wishlist” on any product to save it here.</p>
                <a href="{{ route('shop.home') }}" class="btn-line max-w-xs mx-auto">Continue shopping</a>
            </div>
        @else
            <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-x-1 md:gap-x-2">
                @foreach ($items as $item)
                    @php
                        $product = $item->product;
                        $colour = $item->colour;
                        $image = $colour?->images->first();
                        $inStock = $colour && $colour->variants->sum('stock') > 0;
                    @endphp
                    <article>
                        <a href="{{ $product->url($colour) }}" class="block group">
                            <div class="relative aspect-[3/4] bg-neutral-100 overflow-hidden">
                                @if ($image)
                                    <img src="{{ $image->url() }}" alt="{{ $product->name }}" loading="lazy" class="absolute inset-0 w-full h-full object-cover">
                                @endif
                                @unless ($inStock)
                                    <span class="absolute top-2 left-2 bg-paper label px-2 py-1">Sold out</span>
                                @endunless
                            </div>
                            <div class="pt-2 flex justify-between gap-3">
                                <div class="min-w-0">
                                    <h3 class="label truncate group-hover:underline">{{ $product->name }}</h3>
                                    <p class="label text-mute">{{ $colour?->name }}</p>
                                </div>
                                <p class="label whitespace-nowrap">{{ \App\Support\Money::format($product->price) }}</p>
                            </div>
                        </a>
                        <form method="post" action="{{ route('wishlist.toggle') }}" class="pt-1 pb-6">
                            @csrf
                            <input type="hidden" name="product_id" value="{{ $product->id }}">
                            <input type="hidden" name="colour_id" value="{{ $item->savedColourId }}">
                            <button class="label link-u text-mute">Remove</button>
                        </form>
                    </article>
                @endforeach
            </div>
        @endif
    </div>
@endsection

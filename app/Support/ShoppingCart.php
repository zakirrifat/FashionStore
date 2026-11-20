<?php

namespace App\Support;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;

/**
 * The shopping bag. Stored in the database so it survives closing the browser:
 * guests are recognised by a cookie, signed-in customers by their account.
 */
class ShoppingCart
{
    public const COOKIE = 'cart_token';

    private const COOKIE_DAYS = 30;

    public const MAX_PER_LINE = 10;

    public static function current(): ?Cart
    {
        if ($user = auth()->user()) {
            return Cart::where('user_id', $user->id)->latest('id')->first();
        }
        $token = self::guestToken();

        return $token ? Cart::whereNull('user_id')->where('token', $token)->first() : null;
    }

    /** Guest bag id: from this visit's session, else from the 30-day cookie. */
    private static function guestToken(): ?string
    {
        foreach ([session(self::COOKIE), request()->cookie(self::COOKIE)] as $token) {
            if (is_string($token) && Str::isUuid($token)) {
                return $token;
            }
        }

        return null;
    }

    public static function getOrCreate(): Cart
    {
        if ($cart = self::current()) {
            return $cart;
        }
        $cart = Cart::create(['token' => (string) Str::uuid(), 'user_id' => auth()->id()]);
        if (! auth()->check()) {
            session([self::COOKIE => $cart->token]);
            Cookie::queue(self::COOKIE, $cart->token, 60 * 24 * self::COOKIE_DAYS);
        }

        return $cart;
    }

    /** Add a size to the bag. Returns the quantity now in the bag, limited by stock. */
    public static function add(ProductVariant $variant, int $quantity = 1): int
    {
        $cart = self::getOrCreate();
        $item = CartItem::firstOrNew(['cart_id' => $cart->id, 'product_variant_id' => $variant->id]);
        $item->quantity = min(($item->quantity ?? 0) + max(1, $quantity), $variant->stock, self::MAX_PER_LINE);
        $item->save();
        $cart->touch();

        return $item->quantity;
    }

    public static function update(int $itemId, int $quantity): void
    {
        $item = self::current()?->items()->whereKey($itemId)->with('variant')->first();
        if (! $item) {
            return;
        }
        $quantity = min($quantity, $item->variant->stock, self::MAX_PER_LINE);
        $quantity <= 0 ? $item->delete() : $item->update(['quantity' => $quantity]);
    }

    public static function remove(int $itemId): void
    {
        self::current()?->items()->whereKey($itemId)->delete();
    }

    public static function count(): int
    {
        return (int) (self::current()?->items()->sum('quantity') ?? 0);
    }

    public static function clear(): void
    {
        if ($cart = self::current()) {
            $cart->items()->delete();
            $cart->update(['discount_code' => null]);
        }
    }

    /**
     * Bag lines with everything the pages need. Lines whose product was hidden or
     * deleted are marked unavailable; quantities above stock are flagged.
     *
     * @return Collection<int, object{item: CartItem, variant: ProductVariant, product: Product, colour: \App\Models\ProductColour, image: ?string, unitPrice: int, quantity: int, available: bool, stock: int}>
     */
    public static function lines(?Cart $cart = null): Collection
    {
        $cart ??= self::current();
        if (! $cart) {
            return collect();
        }

        $items = $cart->items()->with(['variant.colour.images', 'variant.colour.product'])->get();
        $visibleIds = Product::visible()->whereIn('id', $items->pluck('variant.colour.product_id')->filter())->pluck('id')->all();

        return $items->map(function (CartItem $item) use ($visibleIds) {
            $variant = $item->variant;
            $colour = $variant?->colour;
            $product = $colour?->product;
            $available = $product && in_array($product->id, $visibleIds, true) && $variant->stock > 0;

            return (object) [
                'item' => $item,
                'variant' => $variant,
                'product' => $product,
                'colour' => $colour,
                'image' => $colour?->images->first()?->path,
                'unitPrice' => $variant ? $variant->price ?? $product?->price ?? 0 : 0,
                'quantity' => $item->quantity,
                'stock' => $variant?->stock ?? 0,
                'available' => $available,
                'tooMany' => $available && $item->quantity > $variant->stock,
            ];
        })->filter(fn ($l) => $l->product !== null)->values();
    }

    /** On sign-in: move the guest bag into the account's bag. */
    public static function mergeGuestCartInto(User $user): void
    {
        $token = self::guestToken();
        $guest = $token ? Cart::whereNull('user_id')->where('token', $token)->first() : null;
        if (! $guest) {
            return;
        }

        $mine = Cart::where('user_id', $user->id)->latest('id')->first();
        if (! $mine) {
            $guest->update(['user_id' => $user->id]);
        } else {
            foreach ($guest->items()->with('variant')->get() as $item) {
                $existing = $mine->items()->where('product_variant_id', $item->product_variant_id)->first();
                $qty = min(($existing->quantity ?? 0) + $item->quantity, $item->variant->stock, self::MAX_PER_LINE);
                if ($qty > 0) {
                    $mine->items()->updateOrCreate(['product_variant_id' => $item->product_variant_id], ['quantity' => $qty]);
                }
            }
            $mine->update(['discount_code' => $mine->discount_code ?? $guest->discount_code]);
            $guest->delete();
        }
        session()->forget(self::COOKIE);
        Cookie::queue(Cookie::forget(self::COOKIE));
    }
}

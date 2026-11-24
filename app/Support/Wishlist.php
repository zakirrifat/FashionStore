<?php

namespace App\Support;

use App\Models\Product;
use App\Models\ProductColour;
use App\Models\User;
use App\Models\WishlistItem;
use App\Services\AdminNotifier;
use Illuminate\Support\Collection;

/**
 * Wishlist for signed-in customers (database) and guests (session).
 * A guest's items move into their account when they sign in or register.
 */
class Wishlist
{
    private const SESSION_KEY = 'wishlist';

    /** @return list<string> keys like "12:34" (product id : colour id) */
    public static function keys(): array
    {
        if ($user = auth()->user()) {
            return $user->wishlistItems()->get(['product_id', 'product_colour_id'])
                ->map(fn ($i) => $i->product_id.':'.$i->product_colour_id)->all();
        }

        return array_values(session(self::SESSION_KEY, []));
    }

    public static function count(): int
    {
        return count(self::keys());
    }

    public static function has(Product $product, ?ProductColour $colour): bool
    {
        return in_array(self::key($product->id, $colour?->id), self::keys(), true);
    }

    /** Add or remove. Returns true when the item is now in the wishlist. */
    public static function toggle(Product $product, ?ProductColour $colour): bool
    {
        $added = ! self::has($product, $colour);

        if ($user = auth()->user()) {
            $added
                ? WishlistItem::firstOrCreate(['user_id' => $user->id, 'product_id' => $product->id, 'product_colour_id' => $colour?->id])
                : WishlistItem::where(['user_id' => $user->id, 'product_id' => $product->id, 'product_colour_id' => $colour?->id])->delete();
            AdminNotifier::wishlistChanged($user, $added);
        } else {
            $keys = collect(session(self::SESSION_KEY, []));
            $key = self::key($product->id, $colour?->id);
            $keys = $added ? $keys->push($key)->unique() : $keys->reject(fn ($k) => $k === $key);
            session([self::SESSION_KEY => $keys->values()->take(100)->all()]);
        }

        return $added;
    }

    /** Products (with the saved colour) for the wishlist page, newest first. */
    public static function items(): Collection
    {
        $pairs = collect(self::keys())->map(fn ($k) => array_map(fn ($v) => $v === '' ? null : (int) $v, explode(':', $k)));
        if (! auth()->check()) {
            $pairs = $pairs->reverse(); // session list is oldest first; the database query is newest first
        }

        $products = Product::visible()->whereIn('id', $pairs->pluck(0))
            ->with('colours.images', 'colours.variants')->withCount('colours')->get()->keyBy('id');

        return $pairs->map(function ($pair) use ($products) {
            [$productId, $colourId] = $pair;
            $product = $products->get($productId);
            if (! $product) {
                return null; // product removed or hidden
            }
            $colour = $product->colours->firstWhere('id', $colourId) ?? $product->colours->first();

            return (object) ['product' => $product, 'colour' => $colour, 'savedColourId' => $colourId];
        })->filter()->values();
    }

    /** Move a guest's session wishlist into the user's account. */
    public static function mergeGuestInto(User $user): void
    {
        $merged = false;

        foreach (session()->pull(self::SESSION_KEY, []) as $key) {
            [$productId, $colourId] = array_pad(explode(':', $key), 2, null);
            if (Product::whereKey($productId)->exists()) {
                $item = WishlistItem::firstOrCreate([
                    'user_id' => $user->id,
                    'product_id' => (int) $productId,
                    'product_colour_id' => $colourId === '' || $colourId === null ? null : (int) $colourId,
                ]);
                $merged = $merged || $item->wasRecentlyCreated;
            }
        }

        if ($merged) {
            AdminNotifier::wishlistChanged($user, true); // one alert for everything saved as a guest
        }
    }

    private static function key(int $productId, ?int $colourId): string
    {
        return $productId.':'.($colourId ?? '');
    }
}

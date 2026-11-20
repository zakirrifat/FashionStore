<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductColour;
use App\Support\Wishlist;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WishlistController extends Controller
{
    public function index(): View
    {
        return view('shop.wishlist', ['items' => Wishlist::items()]);
    }

    /** Add/remove a product colour. Returns JSON for the heart button, or redirects for plain forms. */
    public function toggle(Request $request): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer'],
            'colour_id' => ['nullable', 'integer'],
        ]);

        $product = Product::visible()->findOrFail($data['product_id']);
        $colour = isset($data['colour_id'])
            ? ProductColour::where('product_id', $product->id)->findOrFail($data['colour_id'])
            : null;

        $added = Wishlist::toggle($product, $colour);

        if ($request->expectsJson()) {
            return response()->json(['added' => $added, 'count' => Wishlist::count()]);
        }

        return back()->with('status', $added ? 'Added to your wishlist.' : 'Removed from your wishlist.');
    }
}

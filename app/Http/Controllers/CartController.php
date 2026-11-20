<?php

namespace App\Http\Controllers;

use App\Models\DiscountCode;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\CheckoutTotals;
use App\Support\ShoppingCart;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CartController extends Controller
{
    public function show(): View
    {
        $cart = ShoppingCart::current();
        $lines = ShoppingCart::lines($cart);

        return view('shop.bag', [
            'lines' => $lines,
            'totals' => CheckoutTotals::calculate($lines, 'ZA', null, $cart?->discount_code),
            'discountCode' => $cart?->discount_code,
        ]);
    }

    /** HTML for the slide-out bag. */
    public function drawer(): JsonResponse
    {
        $cart = ShoppingCart::current();
        $lines = ShoppingCart::lines($cart);

        return response()->json([
            'count' => ShoppingCart::count(),
            'html' => view('shop.partials.bag-drawer', [
                'lines' => $lines,
                'totals' => CheckoutTotals::calculate($lines, 'ZA', null, $cart?->discount_code),
            ])->render(),
        ]);
    }

    public function add(Request $request): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'variant_id' => ['required', 'integer'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:10'],
        ]);

        $variant = ProductVariant::with('colour.product')->findOrFail($data['variant_id']);
        abort_unless(Product::visible()->whereKey($variant->colour->product_id)->exists(), 404);

        if ($variant->stock < 1) {
            return $this->respond($request, false, 'Sorry, this size has just sold out.');
        }

        $before = ShoppingCart::current()?->items()->where('product_variant_id', $variant->id)->value('quantity') ?? 0;
        $now = ShoppingCart::add($variant, (int) ($data['quantity'] ?? 1));
        $message = $now > $before ? 'Added to your bag.' : 'You already have all available stock of this size in your bag.';

        return $this->respond($request, $now > $before, $message);
    }

    public function update(Request $request, int $item): JsonResponse|RedirectResponse
    {
        $data = $request->validate(['quantity' => ['required', 'integer', 'min:0', 'max:10']]);
        ShoppingCart::update($item, (int) $data['quantity']);

        return $request->expectsJson() ? response()->json(['count' => ShoppingCart::count()]) : back();
    }

    public function remove(Request $request, int $item): JsonResponse|RedirectResponse
    {
        ShoppingCart::remove($item);

        return $request->expectsJson() ? response()->json(['count' => ShoppingCart::count()]) : back()->with('status', 'Item removed.');
    }

    public function applyDiscount(Request $request): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:50']]);

        $cart = ShoppingCart::current();
        if (! $cart) {
            return back();
        }

        $subtotal = CheckoutTotals::calculate(ShoppingCart::lines($cart))->subtotal;
        $code = DiscountCode::findByCode($data['code']);
        $problem = $code ? $code->problemFor($subtotal) : 'This code doesn’t exist.';
        if ($problem) {
            return back()->withErrors(['code' => $problem])->withInput();
        }

        $cart->update(['discount_code' => $code->code]);

        return back()->with('status', 'Code '.$code->code.' applied: '.$code->label().'.');
    }

    public function removeDiscount(): RedirectResponse
    {
        ShoppingCart::current()?->update(['discount_code' => null]);

        return back();
    }

    private function respond(Request $request, bool $ok, string $message): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['ok' => $ok, 'message' => $message, 'count' => ShoppingCart::count()], $ok ? 200 : 422);
        }

        return back()->with('status', $message);
    }
}

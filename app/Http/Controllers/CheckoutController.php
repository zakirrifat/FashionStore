<?php

namespace App\Http\Controllers;

use App\Models\Address;
use App\Models\Order;
use App\Models\ShippingMethod;
use App\Services\OrderService;
use App\Support\CheckoutTotals;
use App\Support\ShoppingCart;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CheckoutController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        $cart = ShoppingCart::current();
        $lines = ShoppingCart::lines($cart);
        if ($lines->where('available', true)->isEmpty()) {
            return redirect()->route('bag')->with('status', 'Your bag is empty.');
        }

        $user = $request->user();
        $address = $user?->addresses()->first();
        $country = old('country', $address->country ?? 'ZA');
        $zoneMethods = ShippingMethod::availableFor($country)->get();
        $method = $zoneMethods->firstWhere('id', (int) old('shipping_method_id')) ?? $zoneMethods->first();
        $totals = CheckoutTotals::calculate($lines, $country, $method, $cart->discount_code, (bool) old('gift_wrap'));

        return view('shop.checkout', [
            'totals' => $totals,
            'zoneMethods' => $zoneMethods,
            'selectedMethod' => $method?->id,
            'lines' => $lines,
            'user' => $user,
            'savedAddresses' => $user?->addresses ?? collect(),
            'defaults' => [
                'email' => $user->email ?? '',
                'phone' => $address->phone ?? $user->phone ?? '',
                'first_name' => $address->first_name ?? '',
                'last_name' => $address->last_name ?? '',
                'company' => $address->company ?? '',
                'line1' => $address->line1 ?? '',
                'line2' => $address->line2 ?? '',
                'city' => $address->city ?? '',
                'province' => $address->province ?? '',
                'postal_code' => $address->postal_code ?? '',
                'country' => $country,
            ],
            'discountCode' => $cart->discount_code,
        ]);
    }

    /** Live order summary while the customer changes country / delivery / gift wrap. */
    public function quote(Request $request): JsonResponse
    {
        $country = strtoupper((string) $request->query('country', 'ZA'));
        $cart = ShoppingCart::current();
        $method = ShippingMethod::availableFor($country)->find($request->query('shipping_method_id'))
            ?? ShippingMethod::availableFor($country)->first();

        $totals = CheckoutTotals::calculate(ShoppingCart::lines($cart), $country, $method, $cart?->discount_code, $request->boolean('gift_wrap'));

        return response()->json([
            'methods' => ShippingMethod::availableFor($country)->get()->map(fn (ShippingMethod $m) => [
                'id' => $m->id, 'name' => $m->name, 'estimate' => $m->estimate,
                'price' => \App\Support\Money::format($m->priceFor($totals->subtotal - $totals->discount)),
                'free' => $m->priceFor($totals->subtotal - $totals->discount) === 0,
            ]),
            'selected' => $method?->id,
            'html' => view('shop.partials.checkout-summary', ['totals' => $totals])->render(),
        ]);
    }

    public function place(Request $request): RedirectResponse
    {
        $cart = ShoppingCart::current();
        if (! $cart || $cart->items()->doesntExist()) {
            return redirect()->route('bag')->with('status', 'Your bag is empty.');
        }

        $data = $request->validate([
            'email' => ['required', 'email', 'max:150'],
            'phone' => ['required', 'string', 'max:30'],
            'first_name' => ['required', 'string', 'max:60'],
            'last_name' => ['required', 'string', 'max:60'],
            'company' => ['nullable', 'string', 'max:100'],
            'line1' => ['required', 'string', 'max:150'],
            'line2' => ['nullable', 'string', 'max:150'],
            'city' => ['required', 'string', 'max:80'],
            'province' => [Rule::requiredIf($request->input('country') === 'ZA'), 'nullable', 'string', 'max:80'],
            'postal_code' => ['required', 'string', 'max:20'],
            'country' => ['required', Rule::in(array_keys(Address::countries()))],
            'shipping_method_id' => ['required', 'integer'],
            'gift_wrap' => ['nullable', 'boolean'],
            'gift_message' => ['nullable', 'string', 'max:300'],
            'customer_note' => ['nullable', 'string', 'max:1000'],
            'save_address' => ['nullable', 'boolean'],
            'terms' => ['accepted'],
        ], ['terms.accepted' => 'Please accept the terms to place your order.']);

        if ($data['country'] === 'ZA') {
            $request->validate(['province' => [Rule::in(Address::ZA_PROVINCES)]]);
        }

        $method = ShippingMethod::availableFor($data['country'])->find($data['shipping_method_id']);
        if (! $method) {
            return back()->withInput()->withErrors(['shipping_method_id' => 'Please choose a delivery option for this country.']);
        }

        $order = OrderService::place($cart, $data, $method);

        // Signed-in customers can keep the address for next time.
        if ($user = $request->user()) {
            if ($request->boolean('save_address') && $user->addresses()->count() < 20) {
                $exists = $user->addresses()->where('line1', $data['line1'])->where('postal_code', $data['postal_code'])->exists();
                if (! $exists) {
                    $user->addresses()->create(collect($data)->only(['first_name', 'last_name', 'company', 'line1', 'line2', 'city', 'province', 'postal_code', 'country', 'phone'])->all()
                        + ['is_default' => $user->addresses()->doesntExist()]);
                }
            }
        }

        // Remember which orders this browser placed, so the guest can reopen the confirmation.
        session()->push('placed_orders', $order->id);

        return redirect($order->confirmationUrl());
    }

    public function confirmation(Request $request, string $number, string $token): View
    {
        $order = Order::with('items')->where('number', $number)->firstOrFail();
        abort_unless(hash_equals($order->access_token, $token), 404);

        return view('shop.order', [
            'order' => $order,
            'justPlaced' => in_array($order->id, session('placed_orders', []), true) && $order->created_at->gt(now()->subHour()),
            'paymentInstructions' => \App\Models\Setting::get('payment_instructions'),
        ]);
    }
}

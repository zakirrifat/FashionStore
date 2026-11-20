<?php

namespace App\Services;

use App\Mail\OrderPlaced;
use App\Mail\OrderStatusChanged;
use App\Mail\NewOrderForShop;
use App\Models\Cart;
use App\Models\DiscountCode;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\Setting;
use App\Models\ShippingMethod;
use App\Support\CheckoutTotals;
use App\Support\ShoppingCart;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OrderService
{
    /**
     * Turn the bag into an order. Stock is reserved straight away (taken off),
     * because payment happens later by bank transfer.
     *
     * @param  array  $data  validated checkout form
     */
    public static function place(Cart $cart, array $data, ShippingMethod $method): Order
    {
        $order = DB::transaction(function () use ($cart, $data, $method) {
            $lines = ShoppingCart::lines($cart);

            // Lock the sizes being bought so two customers can't buy the last one at the same time.
            $variants = ProductVariant::whereIn('id', $lines->pluck('variant.id'))->lockForUpdate()->get()->keyBy('id');
            $problems = [];
            foreach ($lines as $line) {
                $stock = $variants->get($line->variant->id)?->stock ?? 0;
                if (! $line->available || $stock < $line->quantity) {
                    $what = $line->product->name.' ('.$line->colour->name.', '.trim($line->variant->size.' '.$line->variant->length).')';
                    $problems[] = $stock > 0 && $line->available
                        ? "Only {$stock} left of {$what}. Please update your bag."
                        : "{$what} is no longer available. Please remove it from your bag.";
                }
            }
            if ($lines->isEmpty()) {
                $problems[] = 'Your bag is empty.';
            }
            if ($problems) {
                throw ValidationException::withMessages(['bag' => $problems]);
            }

            $totals = CheckoutTotals::calculate($lines, $data['country'], $method, $cart->discount_code, (bool) ($data['gift_wrap'] ?? false));
            if ($totals->discountProblem) {
                throw ValidationException::withMessages(['discount_code' => $totals->discountProblem]);
            }

            $order = Order::create([
                'number' => 'TMP-'.Str::random(12),
                'access_token' => Str::random(48),
                'user_id' => auth()->id(),
                'status' => 'awaiting_payment',
                'email' => Str::lower($data['email']),
                'phone' => $data['phone'],
                'shipping_address' => collect($data)->only(['first_name', 'last_name', 'company', 'line1', 'line2', 'city', 'province', 'postal_code', 'country'])->all(),
                'shipping_method' => $method->name.($method->estimate ? ' ('.$method->estimate.')' : ''),
                'currency' => 'ZAR',
                'subtotal' => $totals->subtotal,
                'discount_total' => $totals->discount,
                'shipping_total' => $totals->shipping,
                'gift_wrap_total' => $totals->giftWrap,
                'total' => $totals->total,
                'vat_total' => $totals->vat,
                'vat_exempt' => $totals->vatExempt,
                'discount_code' => $totals->discountCode,
                'gift_wrap' => (bool) ($data['gift_wrap'] ?? false),
                'gift_message' => ($data['gift_wrap'] ?? false) ? ($data['gift_message'] ?? null) : null,
                'customer_note' => $data['customer_note'] ?? null,
                'ip' => request()->ip(),
            ]);
            $order->update(['number' => self::prefix().'-'.(100000 + $order->id)]);

            foreach ($totals->lines as $p) {
                $l = $p->line;
                $order->items()->create([
                    'product_id' => $l->product->id,
                    'product_variant_id' => $l->variant->id,
                    'name' => $l->product->name,
                    'colour' => $l->colour->name,
                    'size' => $l->variant->size,
                    'length' => $l->variant->length,
                    'sku' => $l->variant->sku,
                    'image' => $l->image,
                    'unit_price' => $p->unitPrice,
                    'quantity' => $p->quantity,
                    'line_total' => $p->lineTotal,
                ]);
                $variants->get($l->variant->id)->decrement('stock', $p->quantity);
            }

            if ($totals->discountCode) {
                DiscountCode::where('code', $totals->discountCode)->increment('used_count');
            }

            $cart->items()->delete();
            $cart->update(['discount_code' => null]);

            return $order;
        });

        self::sendMail($order->email, new OrderPlaced($order));
        self::sendMail(Setting::get('contact_email'), new NewOrderForShop($order));

        return $order;
    }

    /* ───────── Admin actions ───────── */

    public static function markPaid(Order $order): void
    {
        $order->update(['status' => 'paid', 'paid_at' => now()]);
        self::sendMail($order->email, new OrderStatusChanged($order, 'paid'));
    }

    public static function markShipped(Order $order, ?string $courier, ?string $trackingNumber, ?string $trackingUrl): void
    {
        $order->update([
            'status' => 'shipped', 'shipped_at' => now(),
            'courier' => $courier, 'tracking_number' => $trackingNumber, 'tracking_url' => $trackingUrl,
            'paid_at' => $order->paid_at ?? now(),
        ]);
        self::sendMail($order->email, new OrderStatusChanged($order, 'shipped'));
    }

    public static function markDelivered(Order $order): void
    {
        $order->update(['status' => 'delivered', 'delivered_at' => now()]);
    }

    /** Cancel and put the items back into stock. */
    public static function cancel(Order $order, bool $notifyCustomer = true): void
    {
        if ($order->status === 'cancelled') {
            return;
        }
        DB::transaction(function () use ($order) {
            foreach ($order->items as $item) {
                if ($item->product_variant_id) {
                    ProductVariant::whereKey($item->product_variant_id)->increment('stock', $item->quantity);
                }
            }
            $order->update(['status' => 'cancelled', 'cancelled_at' => now()]);
        });
        if ($notifyCustomer) {
            self::sendMail($order->email, new OrderStatusChanged($order, 'cancelled'));
        }
    }

    public static function prefix(): string
    {
        return strtoupper(Str::limit(preg_replace('/[^A-Za-z]/', '', (string) Setting::get('order_prefix', 'FS')), 4, '')) ?: 'FS';
    }

    /** An email failing must never lose an order. */
    private static function sendMail(?string $to, $mailable): void
    {
        if (blank($to)) {
            return;
        }
        try {
            Mail::to($to)->send($mailable);
        } catch (\Throwable $e) {
            Log::warning('Order email failed: '.$e->getMessage());
        }
    }
}

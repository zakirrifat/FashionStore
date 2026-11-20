<?php

namespace App\Support;

use App\Models\DiscountCode;
use App\Models\Setting;
use App\Models\ShippingMethod;
use Illuminate\Support\Collection;

/**
 * All money maths for the bag and checkout, in cents.
 *
 * Shop prices include South African VAT. Orders shipped outside South Africa
 * are VAT-free exports: each item price has the VAT taken off.
 */
class CheckoutTotals
{
    public function __construct(
        public readonly array $lines,          // [{unitPrice, quantity, lineTotal, line}]
        public readonly int $subtotal,
        public readonly int $discount,
        public readonly ?string $discountCode,
        public readonly ?string $discountProblem,
        public readonly int $shipping,
        public readonly ?ShippingMethod $shippingMethod,
        public readonly int $giftWrap,
        public readonly int $total,
        public readonly int $vat,
        public readonly bool $vatExempt,
        public readonly int $vatRate,
    ) {}

    /**
     * @param  Collection  $lines  from ShoppingCart::lines() (only available lines are counted)
     */
    public static function calculate(
        Collection $lines,
        string $country = 'ZA',
        ?ShippingMethod $method = null,
        ?string $code = null,
        bool $giftWrap = false,
    ): self {
        $rate = (int) Setting::get('vat_rate', 15);
        $exempt = strtoupper($country) !== 'ZA' && (bool) Setting::get('vat_free_exports', true);

        $priced = $lines->filter(fn ($l) => $l->available)->map(function ($l) use ($rate, $exempt) {
            $unit = $exempt ? (int) round($l->unitPrice * 100 / (100 + $rate)) : $l->unitPrice;
            $qty = min($l->quantity, $l->stock);

            return (object) ['line' => $l, 'unitPrice' => $unit, 'quantity' => $qty, 'lineTotal' => $unit * $qty];
        })->values();

        $subtotal = (int) $priced->sum('lineTotal');

        $discount = 0;
        $problem = null;
        $discountCode = null;
        if (filled($code)) {
            $dc = DiscountCode::findByCode($code);
            $problem = $dc ? $dc->problemFor($subtotal) : 'This code doesn’t exist.';
            if (! $problem) {
                $discount = $dc->amountFor($subtotal);
                $discountCode = $dc->code;
            }
        }

        $afterDiscount = $subtotal - $discount;
        $shipping = $method && $priced->isNotEmpty() ? $method->priceFor($afterDiscount) : 0;
        $wrap = $giftWrap ? (int) Setting::get('gift_wrap_price', 0) : 0;
        $total = $afterDiscount + $shipping + $wrap;
        $vat = $exempt ? 0 : (int) round($total * $rate / (100 + $rate));

        return new self($priced->all(), $subtotal, $discount, $discountCode, $problem, $shipping, $method, $wrap, $total, $vat, $exempt, $rate);
    }
}

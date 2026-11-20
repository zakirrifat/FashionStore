<?php

namespace App\Models;

use App\Models\Concerns\LogsAdminActivity;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;

class DiscountCode extends Model
{
    use LogsAdminActivity;

    protected $fillable = ['code', 'type', 'value', 'min_subtotal', 'starts_at', 'ends_at', 'max_uses', 'used_count', 'is_active'];

    protected function casts(): array
    {
        return [
            'value' => 'integer', 'min_subtotal' => 'integer', 'max_uses' => 'integer', 'used_count' => 'integer',
            'starts_at' => 'datetime', 'ends_at' => 'datetime', 'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(fn (DiscountCode $c) => $c->code = strtoupper(trim($c->code)));
    }

    public static function findByCode(?string $code): ?self
    {
        return filled($code) ? static::where('code', strtoupper(trim($code)))->first() : null;
    }

    /** Null when usable for this subtotal, otherwise the reason it can't be used. */
    public function problemFor(int $subtotal): ?string
    {
        return match (true) {
            ! $this->is_active => 'This code is not active.',
            $this->starts_at && $this->starts_at->isFuture() => 'This code is not valid yet.',
            $this->ends_at && $this->ends_at->isPast() => 'This code has expired.',
            $this->max_uses !== null && $this->used_count >= $this->max_uses => 'This code has been used up.',
            $this->min_subtotal !== null && $subtotal < $this->min_subtotal => 'Spend at least '.Money::format($this->min_subtotal).' to use this code.',
            default => null,
        };
    }

    /** Discount in cents for a subtotal (never more than the subtotal). */
    public function amountFor(int $subtotal): int
    {
        $amount = $this->type === 'percent'
            ? (int) round($subtotal * min(100, $this->value) / 100)
            : $this->value;

        return min($amount, $subtotal);
    }

    public function label(): string
    {
        return $this->type === 'percent' ? $this->value.'% off' : Money::format($this->value).' off';
    }
}

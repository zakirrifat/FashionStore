<?php

namespace App\Models;

use App\Models\Concerns\LogsAdminActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ShippingMethod extends Model
{
    use LogsAdminActivity;

    public const ZONES = ['za' => 'South Africa', 'international' => 'International'];

    protected $fillable = ['name', 'zone', 'price', 'free_over', 'estimate', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return ['price' => 'integer', 'free_over' => 'integer', 'is_active' => 'boolean'];
    }

    public static function zoneFor(string $country): string
    {
        return strtoupper($country) === 'ZA' ? 'za' : 'international';
    }

    public function scopeAvailableFor(Builder $query, string $country): Builder
    {
        return $query->where('is_active', true)->where('zone', self::zoneFor($country))->orderBy('sort_order')->orderBy('price');
    }

    /** Price for this order's subtotal (cents). */
    public function priceFor(int $subtotal): int
    {
        return $this->free_over !== null && $subtotal >= $this->free_over ? 0 : $this->price;
    }
}

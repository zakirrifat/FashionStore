<?php

namespace App\Models;

use App\Models\Concerns\LogsAdminActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ProductVariant extends Model
{
    use LogsAdminActivity;

    protected $fillable = [
        'product_colour_id', 'size', 'length', 'sku', 'barcode', 'price', 'stock', 'sort_order',
    ];

    protected function casts(): array
    {
        return ['price' => 'integer', 'stock' => 'integer'];
    }

    protected static function booted(): void
    {
        // Auto-generate SKU like C00081-900-M-REG when left blank in the admin.
        static::saving(function (ProductVariant $variant) {
            if (blank($variant->sku)) {
                $colour = $variant->colour()->with('product')->first();
                $parts = array_filter([
                    $colour?->product?->style_code,
                    $colour?->code,
                    $variant->size,
                    $variant->length ? strtoupper(substr($variant->length, 0, 3)) : null,
                ], fn ($p) => filled($p));
                $variant->sku = strtoupper(Str::slug(implode('-', $parts)));
            }
        });
    }

    public function colour(): BelongsTo
    {
        return $this->belongsTo(ProductColour::class, 'product_colour_id');
    }

    /** Price in cents: the variant override, or the product's base price. */
    public function effectivePrice(): int
    {
        return $this->price ?? $this->colour->product->price;
    }
}

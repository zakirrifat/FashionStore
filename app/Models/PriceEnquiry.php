<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PriceEnquiry extends Model
{
    public const STATUSES = [
        'new' => 'New',
        'contacted' => 'Contacted',
        'closed' => 'Closed',
    ];

    protected $fillable = [
        'product_id', 'product_variant_id', 'product_name', 'colour', 'size', 'sku',
        'name', 'phone', 'email', 'message', 'status', 'admin_note', 'ip',
    ];

    protected $attributes = [
        'status' => 'new',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    /** "Black · M" — the options the customer picked, if any. */
    public function optionsLabel(): string
    {
        return collect([$this->colour, $this->size])->filter()->implode(' · ');
    }
}

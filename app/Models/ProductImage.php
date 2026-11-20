<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Support\Img;
use Illuminate\Support\Facades\Storage;

class ProductImage extends Model
{
    protected $fillable = ['product_colour_id', 'path', 'alt', 'sort_order'];

    protected static function booted(): void
    {
        // Remove the file from storage when the image row is deleted.
        static::deleted(function (ProductImage $image) {
            Storage::disk('public')->delete($image->path);
            Img::forget($image->path);
        });

        // Photo replaced: remove the old file and its resized copies.
        static::updated(function (ProductImage $image) {
            if ($image->wasChanged('path') && ($old = $image->getOriginal('path'))) {
                Storage::disk('public')->delete($old);
                Img::forget($old);
            }
        });
    }

    public function colour(): BelongsTo
    {
        return $this->belongsTo(ProductColour::class, 'product_colour_id');
    }

    /** Resized WebP URL (default 800px wide). */
    public function url(int $width = 800): string
    {
        return Img::url($this->path, $width);
    }

    public function srcset(): string
    {
        return Img::srcset($this->path);
    }

    public function originalUrl(): string
    {
        return Storage::disk('public')->url($this->path);
    }
}

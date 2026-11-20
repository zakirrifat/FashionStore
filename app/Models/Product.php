<?php

namespace App\Models;

use App\Models\Concerns\LogsAdminActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use LogsAdminActivity;

    use SoftDeletes;

    public const STATUSES = ['draft' => 'Draft', 'active' => 'Active', 'archived' => 'Archived'];

    public const GENDERS = ['woman' => 'Woman', 'man' => 'Man', 'unisex' => 'Unisex'];

    protected $fillable = [
        'name', 'slug', 'style_code', 'tagline', 'gender', 'description', 'details',
        'composition', 'care', 'model_info', 'price', 'compare_at_price', 'status',
        'published_at', 'is_featured', 'seo_title', 'seo_description', 'size_guide_id',
    ];

    public function sizeGuide(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(SizeGuide::class);
    }

    /**
     * The size guide to show: the product's own, else the nearest one set on
     * its categories (checking parents too). Null = use the general Size Guide page.
     */
    public function resolveSizeGuide(): ?SizeGuide
    {
        if ($this->size_guide_id) {
            return $this->sizeGuide;
        }

        foreach ($this->categories()->with('parent.parent')->get() as $category) {
            for ($c = $category, $i = 0; $c && $i < 5; $c = $c->parent, $i++) {
                if ($c->size_guide_id) {
                    return SizeGuide::find($c->size_guide_id);
                }
            }
        }

        return null;
    }

    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'compare_at_price' => 'integer',
            'is_featured' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class);
    }

    public function collections(): BelongsToMany
    {
        return $this->belongsToMany(Collection::class);
    }

    public function wishlistItems(): HasMany
    {
        return $this->hasMany(WishlistItem::class);
    }

    public function colours(): HasMany
    {
        return $this->hasMany(ProductColour::class)->orderBy('sort_order');
    }

    public function variants(): HasManyThrough
    {
        return $this->hasManyThrough(ProductVariant::class, ProductColour::class);
    }

    /** Products customers can see on the storefront. */
    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('status', 'active')
            ->where(fn ($q) => $q->whereNull('published_at')->orWhere('published_at', '<=', now()));
    }

    /** True when a "was" price higher than the current price is set. */
    public function isOnSale(): bool
    {
        return $this->compare_at_price !== null && $this->compare_at_price > $this->price;
    }

    public function url(?ProductColour $colour = null): string
    {
        return route('shop.product', ['product' => $this->slug] + ($colour ? ['colour' => $colour->code] : []));
    }

    public function totalStock(): int
    {
        return (int) $this->variants()->sum('stock');
    }
}

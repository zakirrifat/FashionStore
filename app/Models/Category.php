<?php

namespace App\Models;

use App\Models\Concerns\LogsAdminActivity;
use App\Models\Concerns\WarmsImages;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use LogsAdminActivity;

    use WarmsImages;

    protected $fillable = [
        'parent_id', 'name', 'slug', 'description', 'image',
        'sort_order', 'is_active', 'seo_title', 'seo_description', 'size_guide_id',
    ];

    public function resizableImages(): array
    {
        return [$this->image];
    }

    public function sizeGuide(): BelongsTo
    {
        return $this->belongsTo(SizeGuide::class);
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id')->orderBy('sort_order');
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class);
    }

    /** This category's id plus the ids of every category below it. */
    public function descendantAndSelfIds(): array
    {
        $all = static::query()->get(['id', 'parent_id'])->groupBy('parent_id');
        $ids = [$this->id];
        $queue = [$this->id];
        while ($queue) {
            $parentId = array_shift($queue);
            foreach ($all->get($parentId, collect()) as $child) {
                $ids[] = $child->id;
                $queue[] = $child->id;
            }
        }

        return $ids;
    }

    /** "Woman › Clothing › Jeans" — used in admin dropdowns. */
    public function getFullNameAttribute(): string
    {
        $names = [$this->name];
        $parent = $this->parent;
        $guard = 0;
        while ($parent && $guard++ < 10) {
            array_unshift($names, $parent->name);
            $parent = $parent->parent;
        }

        return implode(' › ', $names);
    }
}

<?php

namespace App\Models;

use App\Models\Concerns\LogsAdminActivity;
use App\Models\Concerns\WarmsImages;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Collection extends Model
{
    use LogsAdminActivity;

    use WarmsImages;

    protected $fillable = ['name', 'slug', 'description', 'image', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function resizableImages(): array
    {
        return [$this->image];
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class);
    }
}

<?php

namespace App\Models;

use App\Models\Concerns\LogsAdminActivity;
use Filament\Forms\Components\RichEditor\RichContentRenderer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SizeGuide extends Model
{
    use LogsAdminActivity;

    protected $fillable = ['name', 'body'];

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function bodyHtml(): string
    {
        return $this->body ? RichContentRenderer::make($this->body)->toHtml() : '';
    }
}

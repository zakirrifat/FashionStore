<?php

namespace App\Models;

use App\Models\Concerns\LogsAdminActivity;
use Filament\Forms\Components\RichEditor\RichContentRenderer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Page extends Model
{
    use LogsAdminActivity;

    public const FOOTER_GROUPS = [
        'client_services' => 'Client services',
        'company' => 'Company',
        'legal' => 'Legal',
    ];

    protected $fillable = [
        'title', 'slug', 'body', 'footer_group', 'sort_order', 'is_active', 'seo_title', 'seo_description',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** Sanitised HTML of the rich-text body, safe to echo. */
    public function bodyHtml(): string
    {
        return $this->body ? RichContentRenderer::make($this->body)->toHtml() : '';
    }

    public function url(): string
    {
        return route('shop.page', $this->slug);
    }
}

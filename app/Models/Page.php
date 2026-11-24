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

    /** The only page that has the "Meet Our Leadership" section. */
    public const ABOUT_SLUG = 'about';

    public const PHOTO_SHAPES = [
        'portrait' => 'Portrait card (4:5)',
        'square' => 'Rounded square (1:1)',
        'circle' => 'Circle (1:1)',
    ];

    public const TEAM_SECTION_DEFAULTS = [
        'enabled' => true,
        'heading' => 'Meet Our Leadership',
        'subtitle' => 'The people behind our vision and global partnerships.',
        'shape' => 'portrait',
        'members' => [],
    ];

    protected $fillable = [
        'title', 'slug', 'body', 'team_section', 'footer_group', 'sort_order', 'is_active', 'seo_title', 'seo_description',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'team_section' => 'array'];
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

    public function isAbout(): bool
    {
        return $this->slug === self::ABOUT_SLUG;
    }

    /**
     * The leadership section ready to show, or null when it should not appear
     * (not the About page, switched off, or nobody added yet).
     */
    public function teamSection(): ?array
    {
        $section = $this->team_section ?? [];
        $members = array_values(array_filter($section['members'] ?? [], fn ($m) => filled($m['name'] ?? null)));

        if (! $this->isAbout() || ! ($section['enabled'] ?? false) || $members === []) {
            return null;
        }

        return [
            'heading' => $section['heading'] ?? '',
            'subtitle' => $section['subtitle'] ?? '',
            'shape' => array_key_exists($section['shape'] ?? '', self::PHOTO_SHAPES) ? $section['shape'] : 'portrait',
            'members' => $members,
        ];
    }

    public function url(): string
    {
        return route('shop.page', $this->slug);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Store-wide settings edited in Admin › Settings › Store settings.
 * Values fall back to config/shop.php until saved once.
 */
class Setting extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['key', 'value'];

    protected function casts(): array
    {
        return ['value' => 'json'];
    }

    private const CACHE_KEY = 'store-settings';

    /** All settings: config defaults overlaid with saved values. */
    public static function allValues(): array
    {
        $saved = Cache::rememberForever(self::CACHE_KEY, fn () => static::query()->pluck('value', 'key')->all());

        return array_replace(config('shop.defaults', []), $saved);
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return static::allValues()[$key] ?? $default;
    }

    public static function saveMany(array $values, ?string $logDescription = null): void
    {
        $before = static::allValues();
        foreach ($values as $key => $value) {
            static::updateOrCreate(['key' => $key], ['value' => $value]);
        }
        Cache::forget(self::CACHE_KEY);

        // Activity log: which settings changed (settings have text keys, so no "subject").
        $changed = collect($values)->filter(fn ($v, $k) => ($before[$k] ?? null) !== $v)->keys();
        if ($logDescription && $changed->isNotEmpty()) {
            activity('settings')->event('updated')
                ->withProperties([
                    'old' => collect($before)->only($changed)->all(),
                    'attributes' => collect($values)->only($changed)->all(),
                ])
                ->log($logDescription);
        }
    }
}

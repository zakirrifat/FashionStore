<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;

/**
 * Resized WebP copies of uploaded images, made on first use.
 *
 * Original:  storage/app/public/products/abc.jpg
 * Resized:   storage/app/public/_resized/800/products/abc.jpg.webp
 *
 * Once a copy exists the web server serves it directly as a static file.
 * Until then its URL points at /img/{width}/{path}, which creates it.
 */
class Img
{
    /** Allowed widths (keeps visitors from creating unlimited sizes). */
    public const WIDTHS = [200, 400, 800, 1200, 1600, 2400];

    /** Folders that may be resized. */
    public const FOLDERS = ['products', 'categories', 'collections', 'home', 'pages'];

    private const DIR = '_resized';

    public static function url(?string $path, int $width = 800): string
    {
        if (blank($path)) {
            return '';
        }
        if (! self::canResize($path)) {
            return Storage::disk('public')->url($path);
        }

        $width = self::nearestWidth($width);
        $resized = self::resizedPath($path, $width);

        return Storage::disk('public')->exists($resized)
            ? Storage::disk('public')->url($resized)
            : route('img', ['width' => $width, 'path' => $path]);
    }

    /** srcset attribute value for responsive <img>. */
    public static function srcset(?string $path, array $widths = [400, 800, 1200, 1600]): string
    {
        if (blank($path) || ! self::canResize($path)) {
            return '';
        }

        return collect($widths)->map(fn ($w) => self::url($path, $w).' '.$w.'w')->implode(', ');
    }

    /** Make (if needed) and return the resized file's storage path. Null if not possible. */
    public static function make(string $path, int $width): ?string
    {
        $disk = Storage::disk('public');
        if (! in_array($width, self::WIDTHS, true) || ! self::canResize($path) || ! $disk->exists($path)) {
            return null;
        }

        $resized = self::resizedPath($path, $width);
        if (! $disk->exists($resized)) {
            $disk->makeDirectory(dirname($resized));
            ImageManager::gd()
                ->read($disk->path($path))
                ->scaleDown(width: $width)
                ->toWebp(quality: 82)
                ->save($disk->path($resized));
        }

        return $resized;
    }

    /** Delete every resized copy of an image (when the original is removed or replaced). */
    public static function forget(?string $path): void
    {
        if (blank($path)) {
            return;
        }
        foreach (self::WIDTHS as $w) {
            Storage::disk('public')->delete(self::resizedPath($path, $w));
        }
    }

    public static function canResize(string $path): bool
    {
        return ! str_contains($path, '..')
            && in_array(strtok($path, '/'), self::FOLDERS, true)
            && preg_match('/\.(jpe?g|png|webp|gif|avif)$/i', $path) === 1;
    }

    private static function nearestWidth(int $width): int
    {
        foreach (self::WIDTHS as $w) {
            if ($w >= $width) {
                return $w;
            }
        }

        return end(self::WIDTHS);
    }

    private static function resizedPath(string $path, int $width): string
    {
        return self::DIR.'/'.$width.'/'.$path.'.webp';
    }
}

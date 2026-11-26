<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
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

    /**
     * File types admin image uploads accept (use with FileUpload::acceptedFileTypes()).
     * Not "image/*": that includes SVG, which can run JavaScript when opened from the shop's domain.
     */
    public const UPLOAD_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/avif'];

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

        $width = self::bucket($path, $width);
        $resized = self::resizedPath($path, $width);

        return Storage::disk('public')->exists($resized)
            ? Storage::disk('public')->url($resized)
            : route('img', ['width' => $width, 'path' => $path]);
    }

    /**
     * srcset attribute value for responsive <img>.
     * Widths larger than the original are left out (they'd be the same picture, only slower to make):
     * a 1200px photo lists 400w, 800w and 1200w even when 1600 and 2400 are asked for.
     */
    public static function srcset(?string $path, array $widths = [400, 800, 1200, 1600]): string
    {
        if (blank($path) || ! self::canResize($path)) {
            return '';
        }

        $original = self::size($path)[0] ?? null;

        return collect($widths)
            ->map(fn ($w) => ['bucket' => self::bucket($path, $w), 'w' => $original ? min($w, $original) : $w])
            ->unique('bucket')
            ->map(fn ($e) => self::url($path, $e['bucket']).' '.$e['w'].'w')
            ->implode(', ');
    }

    /** Make (if needed) and return the resized file's storage path. Null if not possible. */
    public static function make(string $path, int $width): ?string
    {
        $disk = Storage::disk('public');
        if (! in_array($width, self::WIDTHS, true) || ! self::canResize($path) || ! $disk->exists($path)) {
            return null;
        }

        $resized = self::resizedPath($path, $width);
        if ($disk->exists($resized)) {
            return $resized;
        }

        // One request makes each copy; others asking at the same moment wait for it instead of repeating the work.
        $lock = Cache::lock('img-make:'.md5($resized), 120);
        try {
            $lock->block(60);
        } catch (\Illuminate\Contracts\Cache\LockTimeoutException) {
            // Still not there after a minute: make it ourselves below.
        }

        try {
            if (! $disk->exists($resized)) {
                $disk->makeDirectory(dirname($resized));
                // Write to a temporary name, then rename: the web server never sees a half-written file.
                $final = $disk->path($resized);
                $temp = $final.'.'.bin2hex(random_bytes(4)).'.tmp';
                ImageManager::gd()
                    ->read($disk->path($path))
                    ->scaleDown(width: $width)
                    ->toWebp(quality: 82)
                    ->save($temp);
                rename($temp, $final);
            }
        } finally {
            if (isset($temp) && is_file($temp)) {
                @unlink($temp);
            }
            $lock->release();
        }

        return $resized;
    }

    /**
     * Make every resized copy a page can ask for (skipping ones larger than the original).
     * Called when an admin saves a photo, so visitors never wait for the resizer.
     */
    public static function warm(?string $path): void
    {
        if (blank($path) || ! self::canResize($path)) {
            return;
        }

        foreach (array_unique(array_map(fn ($w) => self::bucket($path, $w), self::WIDTHS)) as $width) {
            try {
                self::make($path, $width);
            } catch (\Throwable $e) {
                report($e); // a broken image must not stop the save
            }
        }
    }

    /** warm() several images after the response has been sent (the admin doesn't wait either). */
    public static function warmLater(array $paths): void
    {
        $paths = array_values(array_unique(array_filter($paths, fn ($p) => is_string($p) && filled($p) && self::canResize($p))));
        if ($paths) {
            defer(fn () => array_map([self::class, 'warm'], $paths));
        }
    }

    /** Width ÷ height of an uploaded image (remembered, so pages don't re-read the file). Null if unknown. */
    public static function ratio(?string $path): ?float
    {
        $size = blank($path) ? null : self::size($path);

        return $size && $size[1] > 0 ? $size[0] / $size[1] : null;
    }

    /** [width, height] of an uploaded original, remembered. Null if unknown. */
    public static function size(string $path): ?array
    {
        // once(): pages ask for the same photo many times; look it up once per request.
        return once(function () use ($path) {
            if (! self::canResize($path) || ! Storage::disk('public')->exists($path)) {
                return null;
            }

            return Cache::rememberForever('img-size:'.md5($path), function () use ($path) {
                $size = @getimagesize(Storage::disk('public')->path($path));

                return $size && $size[0] > 0 && $size[1] > 0 ? [$size[0], $size[1]] : null;
            });
        });
    }

    /** Delete every resized copy of an image (when the original is removed or replaced). */
    public static function forget(?string $path): void
    {
        if (blank($path)) {
            return;
        }
        \Illuminate\Support\Once::flush();
        Cache::forget('img-size:'.md5($path));
        Cache::forget('img-ratio:'.md5($path)); // older cache key
        foreach (self::WIDTHS as $w) {
            Storage::disk('public')->delete(self::resizedPath($path, $w));
        }
    }

    /** The stored width to use: the nearest allowed width, but never wider than the original needs. */
    private static function bucket(string $path, int $width): int
    {
        $original = self::size($path)[0] ?? null;

        return self::nearestWidth($original ? min($width, $original) : $width);
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

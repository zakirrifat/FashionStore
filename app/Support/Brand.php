<?php

namespace App\Support;

use App\Models\Setting;
use GdImage;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Store logo and icons.
 *
 * The admin uploads one logo (any colour, white background is fine). We make:
 *  - logo_dark  : trimmed, transparent background, near-black  (light pages)
 *  - logo_light : same shape in off-white                       (over photos, dark footer)
 *  - icon_512 / icon_180 / icon_32 : square icons (browser tab, phone home screen)
 *  - og         : 1200×630 share image (WhatsApp, Facebook…)
 * Everything falls back to the text store name when no logo is set.
 */
class Brand
{
    private const DIR = 'brand';

    private const INK = [20, 20, 18];        // #141412

    private const PAPER = [250, 249, 246];   // #faf9f6

    /* ───────── Reading (safe to call anywhere, even before migrations) ───────── */

    public static function get(string $key): ?string
    {
        try {
            $path = Setting::get('brand_'.$key);
        } catch (\Throwable) {
            return null;
        }

        return filled($path) && Storage::disk('public')->exists($path) ? $path : null;
    }

    public static function url(string $key): ?string
    {
        $path = self::get($key);

        return $path ? Storage::disk('public')->url($path) : null;
    }

    public static function hasLogo(): bool
    {
        return self::get('logo_dark') !== null;
    }

    /* ───────── Processing ───────── */

    /**
     * Create all brand files from an uploaded logo (and optional square icon).
     *
     * @return array<string, string> settings to save (brand_* => storage path)
     */
    public static function process(string $logoPath, bool $removeBackground = true, ?string $iconPath = null): array
    {
        $disk = Storage::disk('public');
        $stamp = now()->format('YmdHis').'-'.Str::lower(Str::random(4));
        $src = self::load($disk->path($logoPath));

        $alpha = self::alphaMask($src, $removeBackground);
        [$x, $y, $w, $h] = self::bounds($alpha, imagesx($src), imagesy($src));
        $pad = (int) round(max($w, $h) * 0.02);
        $x = max(0, $x - $pad);
        $y = max(0, $y - $pad);
        $w = min(imagesx($src) - $x, $w + 2 * $pad);
        $h = min(imagesy($src) - $y, $h + 2 * $pad);

        // Keep logos crisp but not huge: max 1600 px wide.
        $scale = min(1, 1600 / $w);
        $outW = (int) round($w * $scale);
        $outH = (int) round($h * $scale);

        $out = [];
        foreach (['logo_dark' => self::INK, 'logo_light' => self::PAPER] as $key => $rgb) {
            $img = self::recolour($src, $alpha, $x, $y, $w, $h, $rgb, $removeBackground);
            $img = self::resize($img, $outW, $outH);
            $out['brand_'.$key] = self::save($img, "{$key}-{$stamp}.png");
        }

        // Icons: from the square icon if given, else from the trimmed logo on a paper background.
        $mark = $iconPath
            ? self::load($disk->path($iconPath))
            : self::resize(self::recolour($src, $alpha, $x, $y, $w, $h, self::INK, $removeBackground), $outW, $outH);
        foreach ([512 => 0.78, 180 => 0.78, 32 => 0.92] as $size => $fill) {
            $out["brand_icon_{$size}"] = self::save(self::onCanvas($mark, $size, $size, $fill, (bool) $iconPath), "icon-{$size}-{$stamp}.png");
        }

        $out['brand_og'] = self::save(self::onCanvas(
            self::resize(self::recolour($src, $alpha, $x, $y, $w, $h, self::INK, $removeBackground), $outW, $outH), 1200, 630, 0.55
        ), "share-{$stamp}.png");

        return $out;
    }

    /** Delete generated files that are no longer used. */
    public static function cleanup(array $keep): void
    {
        $disk = Storage::disk('public');
        foreach ($disk->files(self::DIR.'/generated') as $file) {
            if (! in_array($file, $keep, true)) {
                $disk->delete($file);
            }
        }
    }

    /* ───────── GD helpers ───────── */

    private static function load(string $file): GdImage
    {
        $img = imagecreatefromstring((string) file_get_contents($file));
        if (! $img) {
            throw new \RuntimeException('This logo file could not be read. Please upload a PNG or JPG.');
        }
        imagepalettetotruecolor($img);
        imagealphablending($img, false);
        imagesavealpha($img, true);

        return $img;
    }

    /** 0 = transparent … 127 GD-alpha scale inverted: we store opacity 0–255 per pixel. */
    private static function alphaMask(GdImage $img, bool $removeBackground): array
    {
        $w = imagesx($img);
        $h = imagesy($img);
        $mask = [];
        for ($y = 0; $y < $h; $y++) {
            $row = [];
            for ($x = 0; $x < $w; $x++) {
                $c = imagecolorat($img, $x, $y);
                $a = 255 - (int) round((($c >> 24) & 0x7F) * 255 / 127);   // existing transparency
                if ($removeBackground) {
                    $lum = 0.299 * (($c >> 16) & 0xFF) + 0.587 * (($c >> 8) & 0xFF) + 0.114 * ($c & 0xFF);
                    // White → transparent, dark → solid, smooth edges in between.
                    $ink = $lum >= 235 ? 0 : ($lum <= 60 ? 255 : (int) round((235 - $lum) / 175 * 255));
                    $a = (int) round($a * $ink / 255);
                }
                $row[] = $a;
            }
            $mask[] = $row;
        }

        return $mask;
    }

    private static function bounds(array $mask, int $w, int $h): array
    {
        $minX = $w; $minY = $h; $maxX = 0; $maxY = 0;
        foreach ($mask as $y => $row) {
            foreach ($row as $x => $a) {
                if ($a > 20) {
                    $minX = min($minX, $x); $maxX = max($maxX, $x);
                    $minY = min($minY, $y); $maxY = max($maxY, $y);
                }
            }
        }
        if ($maxX <= $minX || $maxY <= $minY) {
            throw new \RuntimeException('The logo looks empty. Use a dark logo on a white or transparent background.');
        }

        return [$minX, $minY, $maxX - $minX + 1, $maxY - $minY + 1];
    }

    /** Copy the cropped logo as one solid colour, keeping the shape's soft edges. */
    private static function recolour(GdImage $src, array $mask, int $x0, int $y0, int $w, int $h, array $rgb, bool $solid): GdImage
    {
        $img = imagecreatetruecolor($w, $h);
        imagealphablending($img, false);
        imagesavealpha($img, true);
        imagefill($img, 0, 0, imagecolorallocatealpha($img, 0, 0, 0, 127));
        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                $a = $mask[$y0 + $y][$x0 + $x];
                if ($a === 0) {
                    continue;
                }
                if ($solid) {
                    [$r, $g, $b] = $rgb;
                } else {
                    // Keep original colours when the background was already transparent.
                    $c = imagecolorat($src, $x0 + $x, $y0 + $y);
                    [$r, $g, $b] = [($c >> 16) & 0xFF, ($c >> 8) & 0xFF, $c & 0xFF];
                }
                imagesetpixel($img, $x, $y, imagecolorallocatealpha($img, $r, $g, $b, 127 - (int) round($a * 127 / 255)));
            }
        }

        return $img;
    }

    private static function resize(GdImage $img, int $w, int $h): GdImage
    {
        if (imagesx($img) === $w && imagesy($img) === $h) {
            return $img;
        }
        $out = imagecreatetruecolor($w, $h);
        imagealphablending($out, false);
        imagesavealpha($out, true);
        imagefill($out, 0, 0, imagecolorallocatealpha($out, 0, 0, 0, 127));
        imagecopyresampled($out, $img, 0, 0, 0, 0, $w, $h, imagesx($img), imagesy($img));

        return $out;
    }

    /** Centre an image on a paper-coloured canvas (icons, share image). */
    private static function onCanvas(GdImage $mark, int $cw, int $ch, float $fill, bool $cover = false): GdImage
    {
        $canvas = imagecreatetruecolor($cw, $ch);
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, ...self::PAPER));
        imagealphablending($canvas, true);
        $ratio = $cover
            ? max($cw / imagesx($mark), $ch / imagesy($mark))
            : min($cw * $fill / imagesx($mark), $ch * $fill / imagesy($mark));
        $w = (int) round(imagesx($mark) * $ratio);
        $h = (int) round(imagesy($mark) * $ratio);
        imagecopyresampled($canvas, $mark, (int) (($cw - $w) / 2), (int) (($ch - $h) / 2), 0, 0, $w, $h, imagesx($mark), imagesy($mark));

        return $canvas;
    }

    private static function save(GdImage $img, string $name): string
    {
        $path = self::DIR.'/generated/'.$name;
        $disk = Storage::disk('public');
        $disk->makeDirectory(self::DIR.'/generated');
        imagepng($img, $disk->path($path), 8);

        return $path;
    }
}

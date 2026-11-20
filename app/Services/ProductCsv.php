<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Collection;
use App\Models\Product;
use App\Models\ProductColour;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Product spreadsheet import / export. One row = one size of one colour.
 *
 * Only style_code, colour_code and size are required on every row. Any other column
 * that is missing or left empty is not changed, so a file with just
 * style_code, colour_code, size, length, stock updates stock levels only.
 *
 * Prices are in Rand (e.g. 1250 or 1250.50). Categories / collections are URL slugs separated by "|".
 * Photos are not imported — add them in the product editor.
 */
class ProductCsv
{
    public const COLUMNS = [
        'style_code', 'name', 'slug', 'status', 'gender', 'price', 'compare_at_price', 'tagline',
        'description', 'details', 'composition', 'care', 'categories', 'collections',
        'colour_name', 'colour_code', 'colour_hex', 'size', 'length', 'sku', 'barcode', 'stock', 'variant_price',
    ];

    private const REQUIRED = ['style_code', 'colour_code', 'size'];

    /* ───────────── Export ───────────── */

    public static function exportRows(): \Generator
    {
        $products = Product::with(['categories:id,slug', 'collections:id,slug', 'colours.variants'])->orderBy('id')->lazy(100);

        foreach ($products as $p) {
            foreach ($p->colours as $c) {
                foreach ($c->variants->isEmpty() ? [null] : $c->variants as $v) {
                    yield [
                        $p->style_code, $p->name, $p->slug, $p->status, $p->gender,
                        self::rand($p->price), self::rand($p->compare_at_price), $p->tagline,
                        $p->description, $p->details, $p->composition, $p->care,
                        $p->categories->pluck('slug')->implode('|'), $p->collections->pluck('slug')->implode('|'),
                        $c->name, $c->code, $c->hex,
                        $v?->size, $v?->length, $v?->sku, $v?->barcode, $v?->stock, self::rand($v?->price),
                    ];
                }
            }
        }
    }

    public static function templateRows(): array
    {
        return [
            ['C00081', 'Loose fit jeans - 1981', '', 'draft', 'unisex', '6200', '', 'Low waist, baggy leg', 'Jeans in black denim.', "Loose fit\nButton fly", 'Shell: 100% Cotton', 'Machine wash cold', 'woman-jeans|man-jeans', 'new-arrivals', 'Black', '900', '#000000', 'S', 'Regular', '', '', '5', ''],
            ['C00081', '', '', '', '', '', '', '', '', '', '', '', '', '', 'Black', '900', '', 'M', 'Regular', '', '', '8', ''],
            ['C00081', '', '', '', '', '', '', '', '', '', '', '', '', '', 'Mid Blue', '228', '#5b7fa6', 'M', 'Regular', '', '', '3', ''],
        ];
    }

    /* ───────────── Import ───────────── */

    /**
     * @param  array<int, array<string, string>>  $rows  from Csv::read()
     * @return array{ok: bool, errors: list<string>, summary: array<string, int>}
     */
    public static function import(array $rows): array
    {
        $errors = [];
        $summary = ['products_created' => 0, 'products_updated' => 0, 'sizes_created' => 0, 'sizes_updated' => 0];

        if ($rows === []) {
            return ['ok' => false, 'errors' => ['The file has no rows.'], 'summary' => $summary];
        }
        $missing = array_diff(self::REQUIRED, array_keys($rows[0]));
        if ($missing) {
            return ['ok' => false, 'errors' => ['Missing column(s): '.implode(', ', $missing).'. Download the template to see the format.'], 'summary' => $summary];
        }

        $categorySlugs = Category::pluck('id', 'slug');
        $collectionSlugs = Collection::pluck('id', 'slug');

        // ── Pass 1: check every row. Nothing is saved if any row has a problem. ──
        $products = [];   // style_code => product-level values
        $seenVariants = [];
        $seenSkus = [];
        foreach ($rows as $i => $row) {
            $line = $i + 2; // +1 for header, +1 for 1-based
            $err = function (string $msg) use (&$errors, $line) {
                $errors[] = "Row {$line}: {$msg}";
            };
            $v = fn (string $key) => trim((string) ($row[$key] ?? ''));

            foreach (self::REQUIRED as $col) {
                if ($v($col) === '') {
                    $err("{$col} is empty.");
                }
            }
            $style = $v('style_code');
            if ($style === '') {
                continue;
            }

            $p = $products[$style] ?? ['fields' => [], 'categories' => null, 'collections' => null];
            foreach (['name', 'slug', 'tagline', 'description', 'details', 'composition', 'care'] as $f) {
                if ($v($f) !== '' && ! isset($p['fields'][$f])) {
                    $p['fields'][$f] = $f === 'slug' ? Str::slug($v($f)) : $v($f);
                }
            }
            if ($v('status') !== '') {
                in_array($v('status'), array_keys(Product::STATUSES), true)
                    ? $p['fields']['status'] ??= $v('status')
                    : $err('status must be draft, active or archived.');
            }
            if ($v('gender') !== '') {
                in_array($v('gender'), array_keys(Product::GENDERS), true)
                    ? $p['fields']['gender'] ??= $v('gender')
                    : $err('gender must be woman, man or unisex.');
            }
            foreach (['price', 'compare_at_price'] as $f) {
                if ($v($f) !== '') {
                    ($cents = self::cents($v($f))) === null ? $err("{$f} “{$v($f)}” is not a valid amount.") : $p['fields'][$f] ??= $cents;
                }
            }
            foreach (['categories' => $categorySlugs, 'collections' => $collectionSlugs] as $f => $known) {
                if ($v($f) !== '' && $p[$f] === null) {
                    $slugs = array_filter(array_map('trim', explode('|', $v($f))));
                    $unknown = array_diff($slugs, $known->keys()->all());
                    $unknown ? $err("unknown {$f}: ".implode(', ', $unknown).'.') : $p[$f] = $known->only($slugs)->values()->all();
                }
            }

            $variantKey = $style.'|'.$v('colour_code').'|'.Str::upper($v('size')).'|'.$v('length');
            if (isset($seenVariants[$variantKey])) {
                $err("same colour/size/length as row {$seenVariants[$variantKey]}.");
            }
            $seenVariants[$variantKey] = $line;

            if ($v('stock') !== '' && ! ctype_digit($v('stock'))) {
                $err('stock must be a whole number (0 or more).');
            }
            if ($v('variant_price') !== '' && self::cents($v('variant_price')) === null) {
                $err('variant_price is not a valid amount.');
            }
            if ($v('colour_hex') !== '' && ! preg_match('/^#[0-9a-f]{6}$/i', $v('colour_hex'))) {
                $err('colour_hex must look like #1a2b3c.');
            }
            if ($v('length') !== '' && ! in_array($v('length'), ['Regular', 'Petit', 'Long'], true)) {
                $err('length must be Regular, Petit or Long (or empty).');
            }
            if ($v('sku') !== '') {
                if (isset($seenSkus[$v('sku')])) {
                    $err("SKU {$v('sku')} is also used on row {$seenSkus[$v('sku')]}.");
                }
                $seenSkus[$v('sku')] = $line;
            }

            $p['rows'][] = $row + ['_line' => $line];
            $products[$style] = $p;
        }

        // Product-level checks.
        $existing = Product::withTrashed()->whereIn('style_code', array_keys($products))->get()->keyBy('style_code');
        foreach ($products as $style => $p) {
            $product = $existing->get($style);
            if ($product?->trashed()) {
                $errors[] = "{$style}: this product is in the bin. Restore it first.";
            }
            if (! $product) {
                foreach (['name', 'price'] as $f) {
                    if (! isset($p['fields'][$f])) {
                        $errors[] = "{$style}: new product needs a {$f} (on at least one of its rows).";
                    }
                }
            }
            $slug = $p['fields']['slug'] ?? null;
            if ($slug && Product::withTrashed()->where('slug', $slug)->where('style_code', '!=', $style)->exists()) {
                $errors[] = "{$style}: slug “{$slug}” is already used by another product.";
            }
        }
        // SKUs already used by a different size elsewhere.
        if ($seenSkus) {
            $taken = ProductVariant::with('colour.product')->whereIn('sku', array_keys($seenSkus))->get();
            foreach ($taken as $variant) {
                $key = $variant->colour?->product?->style_code.'|'.$variant->colour?->code.'|'.Str::upper($variant->size).'|'.$variant->length;
                if (! isset($seenVariants[$key])) {
                    $errors[] = "Row {$seenSkus[$variant->sku]}: SKU {$variant->sku} already belongs to another product size.";
                }
            }
        }

        if ($errors) {
            return ['ok' => false, 'errors' => $errors, 'summary' => $summary];
        }

        // ── Pass 2: save everything in one transaction. ──
        DB::transaction(function () use ($products, $existing, &$summary) {
            foreach ($products as $style => $p) {
                $product = $existing->get($style);
                $fields = $p['fields'];

                if (! $product) {
                    $fields['slug'] ??= self::uniqueSlug($fields['name']);
                    $product = Product::create($fields + ['style_code' => $style, 'status' => 'draft', 'gender' => 'unisex']);
                    $summary['products_created']++;
                } else {
                    $product->fill($fields);
                    if ($product->isDirty()) {
                        $product->save();
                    }
                    $summary['products_updated']++;
                }

                if ($p['categories'] !== null) {
                    $product->categories()->sync($p['categories']);
                }
                if ($p['collections'] !== null) {
                    $product->collections()->sync($p['collections']);
                }

                foreach ($p['rows'] as $row) {
                    $v = fn (string $key) => trim((string) ($row[$key] ?? ''));

                    $colour = ProductColour::firstOrNew(['product_id' => $product->id, 'code' => $v('colour_code')]);
                    if (! $colour->exists) {
                        $colour->name = $v('colour_name') ?: $v('colour_code');
                        $colour->sort_order = $product->colours()->count();
                    } elseif ($v('colour_name') !== '') {
                        $colour->name = $v('colour_name');
                    }
                    if ($v('colour_hex') !== '') {
                        $colour->hex = $v('colour_hex');
                    }
                    $colour->save();

                    $length = $v('length') ?: null;
                    $variant = $colour->variants()->get()->first(fn ($x) => Str::upper($x->size) === Str::upper($v('size')) && $x->length === $length)
                        ?? new ProductVariant(['product_colour_id' => $colour->id, 'size' => $v('size'), 'length' => $length, 'stock' => 0, 'sort_order' => $colour->variants()->count()]);
                    $isNew = ! $variant->exists;

                    if ($v('stock') !== '') {
                        $variant->stock = (int) $v('stock');
                    }
                    foreach (['sku', 'barcode'] as $f) {
                        if ($v($f) !== '') {
                            $variant->{$f} = $v($f);
                        }
                    }
                    if ($v('variant_price') !== '') {
                        $variant->price = self::cents($v('variant_price'));
                    }
                    $variant->save();
                    $isNew ? $summary['sizes_created']++ : $summary['sizes_updated']++;
                }
            }
        });

        return ['ok' => true, 'errors' => [], 'summary' => $summary];
    }

    /* ───────────── Helpers ───────────── */

    /** "1 250,50" / "R1250.5" / "1250" → cents, or null if not a number. */
    public static function cents(string $value): ?int
    {
        $clean = str_replace([' ', "\u{00A0}", 'R', 'r'], '', $value);
        if (preg_match('/^\d+,\d{1,2}$/', $clean)) {
            $clean = str_replace(',', '.', $clean); // decimal comma
        }
        $clean = str_replace(',', '', $clean);   // thousands comma

        return is_numeric($clean) && (float) $clean >= 0 ? (int) round(((float) $clean) * 100) : null;
    }

    private static function rand(?int $cents): string
    {
        return $cents === null ? '' : rtrim(rtrim(number_format($cents / 100, 2, '.', ''), '0'), '.');
    }

    private static function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'product';
        $slug = $base;
        for ($i = 2; Product::withTrashed()->where('slug', $slug)->exists(); $i++) {
            $slug = "{$base}-{$i}";
        }

        return $slug;
    }
}

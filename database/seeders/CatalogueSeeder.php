<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Collection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Starter category tree, modelled on a fashion-house menu structure.
 * Safe to run more than once.
 */
class CatalogueSeeder extends Seeder
{
    public function run(): void
    {
        // Clothing only — the store does not sell shoes, bags or accessories.
        $clothing = ['Jeans', 'Outerwear', 'Sweatshirts', 'Knitwear', 'Trousers', 'T-shirts', 'Shirts', 'Suit Jackets', 'Shorts'];

        $tree = [
            'Woman' => [
                'Clothing' => array_merge($clothing, ['Dresses', 'Skirts']),
            ],
            'Man' => [
                'Clothing' => $clothing,
            ],
            'Kids' => [
                'Girls' => ['Dresses', 'Skirts', 'T-shirts', 'Sweatshirts', 'Knitwear', 'Jeans', 'Trousers', 'Outerwear', 'Shorts'],
                'Boys' => ['T-shirts', 'Shirts', 'Sweatshirts', 'Knitwear', 'Jeans', 'Trousers', 'Outerwear', 'Shorts'],
            ],
        ];

        $sort = 0;
        foreach ($tree as $top => $groups) {
            $topCat = $this->category($top, null, $sort++);
            $groupSort = 0;
            foreach ($groups as $group => $children) {
                $groupCat = $this->category($group, $topCat, $groupSort++);
                foreach (array_values($children) as $i => $child) {
                    $this->category($child, $groupCat, $i);
                }
            }
        }

        foreach (['New Arrivals', 'Gift Guide', 'Winter Dressing', 'SS27', 'FW26'] as $i => $name) {
            Collection::firstOrCreate(['slug' => Str::slug($name)], ['name' => $name, 'sort_order' => $i]);
        }
    }

    private function category(string $name, ?Category $parent, int $sort): Category
    {
        // Slug includes the parent path so "Woman › Jeans" and "Man › Jeans" don't clash.
        $slug = Str::slug(($parent ? $parent->slug.' ' : '').$name);
        $slug = str_replace(['woman-clothing-', 'man-clothing-'], ['woman-', 'man-'], $slug);

        return Category::firstOrCreate(
            ['slug' => $slug],
            ['name' => $name, 'parent_id' => $parent?->id, 'sort_order' => $sort, 'is_active' => true],
        );
    }
}

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
        $clothing = ['Jeans', 'Outerwear', 'Sweatshirts', 'Knitwear', 'Trousers', 'T-shirts', 'Shirts', 'Suit Jackets', 'Shorts'];
        $accessories = ['Scarves', 'Bags', 'Hats', 'Shoes', 'Small Leather Goods', 'Jewellery', 'Eyewear', 'Belts'];

        $tree = [
            'Woman' => [
                'Clothing' => array_merge($clothing, ['Dresses', 'Skirts']),
                'Shoes & Accessories' => $accessories,
            ],
            'Man' => [
                'Clothing' => $clothing,
                'Shoes & Accessories' => $accessories,
            ],
            'Bags' => ['Shoulder Bags' => [], 'Top Handle Bags' => [], 'Backpacks' => [], 'Crossbody Bags' => [], 'Totes' => [], 'Micro Bags' => []],
            'Scarves' => ['Cashmere' => [], 'Wool' => [], 'Silk' => [], 'Printed' => []],
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
        $slug = str_replace(['woman-clothing-', 'man-clothing-', 'woman-shoes-accessories-', 'man-shoes-accessories-'], ['woman-', 'man-', 'woman-', 'man-'], $slug);

        return Category::firstOrCreate(
            ['slug' => $slug],
            ['name' => $name, 'parent_id' => $parent?->id, 'sort_order' => $sort, 'is_active' => true],
        );
    }
}

<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchPanelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function product(string $name, bool $featured = false, string $status = 'active'): Product
    {
        $product = Product::create([
            'name' => $name, 'slug' => str($name)->slug(), 'style_code' => strtoupper(str($name)->slug('')),
            'price' => 100000, 'status' => $status, 'is_featured' => $featured,
        ]);
        $product->colours()->create(['name' => 'Black', 'code' => '100']);

        return $product;
    }

    public function test_panel_is_on_every_shop_page_with_categories(): void
    {
        Category::create(['name' => 'Woman', 'slug' => 'woman', 'is_active' => true]);

        $this->get('/contact')->assertOk()
            ->assertSee('Search products, colours, styles…')
            ->assertSee('Shop by category')
            ->assertSee('/c/woman', false)
            ->assertDontSee('What are you looking for?');
    }

    public function test_empty_search_returns_only_featured_products_with_enquiry_details(): void
    {
        $this->product('Plain Shirt');
        $featured = $this->product('Wool Coat', featured: true);
        $this->product('Hidden Coat', featured: true, status: 'draft');

        $this->getJson('/search/suggest')->assertOk()
            ->assertJsonPath('featured_label', 'Featured')
            ->assertJsonCount(1, 'featured')
            ->assertJsonPath('featured.0.name', 'Wool Coat')
            ->assertJsonPath('featured.0.enquiry.id', $featured->id)
            ->assertJsonMissingPath('featured.0.price');
    }

    public function test_falls_back_to_newest_when_nothing_is_featured(): void
    {
        $this->product('Plain Shirt');

        $this->getJson('/search/suggest')->assertJsonPath('featured_label', 'New in')
            ->assertJsonPath('featured.0.name', 'Plain Shirt');
    }

    public function test_typing_returns_products_and_reachable_categories_with_their_path(): void
    {
        $this->product('Wool Coat');
        $man = Category::create(['name' => 'Man', 'slug' => 'man', 'is_active' => true]);
        Category::create(['name' => 'Coats', 'slug' => 'man-coats', 'parent_id' => $man->id, 'is_active' => true]);
        $hidden = Category::create(['name' => 'Archive', 'slug' => 'archive', 'is_active' => false]);
        Category::create(['name' => 'Old Coats', 'slug' => 'old-coats', 'parent_id' => $hidden->id, 'is_active' => true]);

        $this->getJson('/search/suggest?q=coat')->assertOk()
            ->assertJsonPath('results.0.name', 'Wool Coat')
            ->assertJsonCount(1, 'categories')
            ->assertJsonPath('categories.0.name', 'Man / Coats')
            ->assertJsonMissingPath('featured');
    }

    public function test_no_matches_returns_empty_lists(): void
    {
        $this->product('Wool Coat');

        $this->getJson('/search/suggest?q=zzzz')->assertOk()
            ->assertJsonCount(0, 'results')->assertJsonCount(0, 'categories');
    }
}

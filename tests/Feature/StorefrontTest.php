<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Collection;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontTest extends TestCase
{
    use RefreshDatabase;

    private Category $man;

    private Category $jackets;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        $this->man = Category::create(['name' => 'Man', 'slug' => 'man']);
        $clothing = Category::create(['name' => 'Clothing', 'slug' => 'man-clothing', 'parent_id' => $this->man->id]);
        $this->jackets = Category::create(['name' => 'Outerwear', 'slug' => 'man-outerwear', 'parent_id' => $clothing->id]);
    }

    private function product(array $attrs = [], array $sizes = ['M' => 4, 'L' => 0]): Product
    {
        $product = Product::create($attrs + [
            'name' => 'Leather Jacket', 'slug' => 'leather-jacket', 'style_code' => 'J00001',
            'price' => 1250000, 'status' => 'active',
        ]);
        $product->categories()->attach($this->jackets);
        $colour = $product->colours()->create(['name' => 'Black', 'code' => '900']);
        $colour->images()->create(['path' => 'products/black.jpg']);
        foreach ($sizes as $size => $stock) {
            $colour->variants()->create(['size' => $size, 'stock' => $stock]);
        }

        return $product;
    }

    public function test_home_page_shows_active_products(): void
    {
        $this->product(['is_featured' => true]);

        $this->get('/')->assertOk()->assertSee('Leather Jacket')->assertSee('R 12 500');
    }

    public function test_parent_category_includes_products_from_child_categories(): void
    {
        $this->product();

        $this->get('/c/man')->assertOk()->assertSee('Leather Jacket')->assertSee('1 item');
        $this->get('/c/man-outerwear')->assertOk()->assertSee('Leather Jacket');
    }

    public function test_draft_and_future_products_are_hidden(): void
    {
        $this->product(['status' => 'draft']);
        $this->product(['slug' => 'later', 'style_code' => 'J2', 'name' => 'Future Coat', 'published_at' => now()->addDay()]);

        $this->get('/c/man')->assertOk()->assertDontSee('Leather Jacket')->assertDontSee('Future Coat');
        $this->get('/p/leather-jacket')->assertNotFound();
        $this->get('/p/later')->assertNotFound();
    }

    public function test_product_page_shows_details_sizes_and_seo_data(): void
    {
        $this->product(['tagline' => 'Boxy fit', 'details' => "Zip front\nTwo pockets"]);

        $this->get('/p/leather-jacket')->assertOk()
            ->assertSee('Leather Jacket')
            ->assertSee('Boxy fit')
            ->assertSee('Two pockets')
            ->assertSee('R 12 500')
            ->assertSee('"@type":"Product"', false)
            ->assertSee('Outerwear');
    }

    public function test_size_filter_only_matches_in_stock_sizes(): void
    {
        $this->product();

        $this->get('/c/man?size[]=M')->assertSee('Leather Jacket');
        $this->get('/c/man?size[]=L')->assertDontSee('Leather Jacket')->assertSee('No products found');
    }

    public function test_search_and_collections(): void
    {
        $product = $this->product();
        $new = Collection::create(['name' => 'New Arrivals', 'slug' => 'new-arrivals']);
        $product->collections()->attach($new);

        $this->get('/search?q=leather')->assertOk()->assertSee('Leather Jacket');
        $this->get('/search?q=zzz')->assertOk()->assertSee('No products found');
        $this->get('/collections/new-arrivals')->assertOk()->assertSee('Leather Jacket');
    }

    public function test_inactive_category_is_not_found(): void
    {
        $this->man->update(['is_active' => false]);

        $this->get('/c/man')->assertNotFound();
    }
}

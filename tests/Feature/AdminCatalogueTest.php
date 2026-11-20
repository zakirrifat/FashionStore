<?php

namespace Tests\Feature;

use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class AdminCatalogueTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public function test_guests_are_sent_to_the_admin_login(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
    }

    public function test_customers_cannot_enter_the_admin_panel(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);

        $this->actingAs($customer)->get('/admin')->assertForbidden();
    }

    public function test_staff_can_enter_admin_but_not_manage_users(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);

        $this->actingAs($staff)->get('/admin')->assertOk();
        $this->actingAs($staff)->get('/admin/users')->assertForbidden();
    }

    public function test_admin_pages_load(): void
    {
        $this->actingAs($this->admin());

        foreach (['/admin', '/admin/products', '/admin/products/create', '/admin/categories', '/admin/collections', '/admin/users'] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_admin_can_create_a_product_with_colours_photos_and_sizes(): void
    {
        Storage::fake('public');
        $this->actingAs($this->admin());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $jeans = Category::create(['name' => 'Jeans', 'slug' => 'woman-jeans']);

        $component = Livewire::test(CreateProduct::class);

        $component->fillForm([
            'name' => 'Loose fit jeans - 1981',
            'slug' => 'loose-fit-jeans-1981',
            'style_code' => 'C00081',
            'tagline' => 'Low waist, baggy leg',
            'gender' => 'unisex',
            'status' => 'active',
            'price' => '6200.50',
            'categories' => [$jeans->id],
            'colours' => [
                [
                    'name' => 'Black',
                    'code' => '900',
                    'hex' => '#000000',
                    'images' => [
                        ['path' => [UploadedFile::fake()->image('front.jpg', 1200, 1600)], 'alt' => 'Front'],
                    ],
                    'variants' => [
                        ['size' => 'S', 'length' => 'Regular', 'stock' => 3],
                        ['size' => 'M', 'length' => null, 'stock' => 5, 'sku' => 'CUSTOM-SKU'],
                    ],
                ],
            ],
        ])->call('create')->assertHasNoFormErrors();

        $product = Product::with('colours.images', 'colours.variants', 'categories')->sole();

        $this->assertSame(620050, $product->price, 'Rand price is stored as cents');
        $this->assertTrue($product->categories->contains($jeans));
        $this->assertCount(1, $product->colours);

        $colour = $product->colours->first();
        $this->assertSame('Black', $colour->name);
        $this->assertCount(1, $colour->images);
        Storage::disk('public')->assertExists($colour->images->first()->path);

        $skus = $colour->variants->pluck('sku')->all();
        $this->assertContains('C00081-900-S-REG', $skus, 'Blank SKU is generated');
        $this->assertContains('CUSTOM-SKU', $skus, 'Typed SKU is kept');
        $this->assertSame(8, $product->totalStock());
    }

    public function test_product_list_shows_products(): void
    {
        $this->actingAs($this->admin());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $product = Product::create([
            'name' => 'Wool scarf', 'slug' => 'wool-scarf', 'style_code' => 'CA0001',
            'price' => 150000, 'status' => 'active',
        ]);

        Livewire::test(ListProducts::class)->assertCanSeeTableRecords([$product]);
    }
}

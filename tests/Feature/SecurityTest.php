<?php

namespace Tests\Feature;

use App\Filament\Resources\Categories\Pages\CreateCategory;
use App\Models\Product;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/** Security checks: escaping, uploads, headers and who can reach what. */
class SecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function product(array $attrs = []): Product
    {
        $product = Product::create($attrs + [
            'name' => 'Coat', 'slug' => 'coat', 'style_code' => 'C00001', 'price' => 100000, 'status' => 'active',
        ]);
        $colour = $product->colours()->create(['name' => 'Black', 'code' => '900']);
        $colour->variants()->create(['size' => 'M', 'stock' => 1]);

        return $product;
    }

    /* ───── Escaping ───── */

    public function test_product_text_cannot_break_out_of_the_seo_script_block(): void
    {
        // Staff can type product names; they must never run as script on the shop (or in an admin's browser).
        $this->product([
            'name' => 'Coat</script><script>alert(1)</script>',
            'description' => '<p>Warm</p></script><img src=x onerror=alert(2)>',
        ]);

        $html = $this->get('/p/coat')->assertOk()->getContent();

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringNotContainsString('<img src=x onerror=alert(2)>', $html); // shown only as escaped text

        // The structured data is still valid JSON with the real name in it.
        preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $html, $m);
        $this->assertSame('Coat</script><script>alert(1)</script>', json_decode($m[1], true)['name']);
    }

    public function test_search_term_is_escaped(): void
    {
        $this->get('/search?q='.urlencode('<script>alert(1)</script>'))
            ->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_very_long_search_terms_are_cut_short(): void
    {
        $this->get('/search?q='.str_repeat('a', 5000))->assertOk()->assertDontSee(str_repeat('a', 101));
        $this->getJson('/search/suggest?q='.str_repeat('a', 5000))->assertOk();
    }

    /* ───── Uploads ───── */

    public function test_admin_image_uploads_reject_svg_files(): void
    {
        // An SVG on the shop's own domain can run JavaScript when opened directly.
        Storage::fake('public');
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(User::factory()->create(['role' => 'staff']));

        $svg = UploadedFile::fake()->createWithContent('logo.svg',
            '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(document.cookie)</script></svg>');

        Livewire::test(CreateCategory::class)
            ->fillForm(['name' => 'Evil', 'slug' => 'evil', 'image' => [$svg]])
            ->call('create')
            ->assertHasFormErrors(['image']);

        $this->assertSame([], Storage::disk('public')->allFiles('categories'));
    }

    public function test_admin_image_uploads_accept_normal_photos(): void
    {
        Storage::fake('public');
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(User::factory()->create(['role' => 'staff']));

        Livewire::test(CreateCategory::class)
            ->fillForm(['name' => 'Coats', 'slug' => 'coats', 'image' => [UploadedFile::fake()->image('coat.jpg', 800, 600)]])
            ->call('create')
            ->assertHasNoFormErrors();
    }

    /* ───── Response headers ───── */

    public function test_shop_pages_send_security_headers(): void
    {
        $response = $this->get('/');

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $this->assertNotNull($response->headers->get('Permissions-Policy'));
        $response->assertHeaderMissing('Strict-Transport-Security'); // only over HTTPS
    }

    public function test_admin_pages_send_security_headers(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $this->get('/admin')->assertOk()
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_https_responses_turn_on_hsts(): void
    {
        $this->get('https://localhost/')->assertHeader('Strict-Transport-Security');
    }

    /* ───── Who can reach what ───── */

    public function test_staff_cannot_open_admin_only_screens(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($staff);

        // Team (could otherwise promote themselves to admin), store settings and the activity log.
        $this->get('/admin/users')->assertForbidden();
        $this->get('/admin/users/create')->assertForbidden();
        $this->get("/admin/users/{$admin->id}/edit")->assertForbidden();
        $this->get("/admin/users/{$staff->id}/edit")->assertForbidden();
        $this->get('/admin/manage-settings')->assertForbidden();
        $this->get('/admin/activity-logs')->assertForbidden();
    }

    public function test_customers_cannot_open_any_admin_screen(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'customer']));

        foreach (['/admin', '/admin/products', '/admin/customers', '/admin/price-enquiries', '/admin/users'] as $url) {
            $this->get($url)->assertForbidden();
        }
    }

    public function test_customers_cannot_see_or_change_another_customers_address(): void
    {
        config(['shop.checkout_enabled' => true]);
        $owner = User::factory()->create(['role' => 'customer']);
        $other = User::factory()->create(['role' => 'customer']);
        $address = $owner->addresses()->create([
            'first_name' => 'A', 'last_name' => 'B', 'line1' => '1 St', 'city' => 'Durban',
            'province' => 'KwaZulu-Natal', 'postal_code' => '4001', 'country' => 'ZA', 'phone' => '1',
        ]);

        $this->actingAs($other);
        $this->get("/account/addresses/{$address->id}/edit")->assertNotFound();
        $this->put("/account/addresses/{$address->id}", ['first_name' => 'Hacked'])->assertNotFound();
        $this->delete("/account/addresses/{$address->id}")->assertNotFound();

        $this->assertSame('A', $address->fresh()->first_name);
    }
}

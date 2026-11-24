<?php

namespace Tests\Feature;

use App\Filament\Resources\PriceEnquiries\Pages\ListPriceEnquiries;
use App\Models\PriceEnquiry;
use App\Models\Product;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PriceEnquiryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    private function enquiry(array $attrs = []): PriceEnquiry
    {
        return PriceEnquiry::create($attrs + [
            'product_name' => 'Linen Shirt',
            'colour' => 'Black',
            'size' => 'M',
            'name' => 'Nadia Customer',
            'phone' => '+27 82 000 0000',
        ]);
    }

    private function product(array $attrs = []): Product
    {
        $product = Product::create($attrs + [
            'name' => 'Linen Shirt', 'slug' => 'linen-shirt', 'style_code' => 'S00001',
            'price' => 99900, 'status' => 'active',
        ]);
        $colour = $product->colours()->create(['name' => 'Black', 'code' => '900']);
        $colour->variants()->create(['size' => 'M', 'stock' => 2, 'sku' => $product->style_code.'-900-M']);

        return $product;
    }

    /* ───── Shop: sending a request ───── */

    public function test_product_page_and_card_show_ask_for_price(): void
    {
        $this->product(['is_featured' => true]);

        $this->get('/p/linen-shirt')->assertOk()->assertSee('Ask for price')->assertDontSee('Add to bag');
        $this->get('/')->assertOk()->assertSee('Ask for price')->assertDontSee('Quick add');
    }

    public function test_no_prices_or_sale_labels_anywhere_in_the_shop(): void
    {
        $product = $this->product(['price' => 99900, 'compare_at_price' => 150000, 'is_featured' => true]);
        \App\Models\Setting::saveMany(['home_sections' => [
            ['type' => 'products', 'data' => ['title' => 'Featured', 'source' => 'featured']],
            ['type' => 'products', 'data' => ['title' => 'On Sale Now', 'source' => 'sale']],
        ]]);

        $this->get('/')->assertOk()->assertSee('Featured')->assertDontSee('On Sale Now')->assertDontSee('R 999')->assertDontSee('>Sale<', false);
        $this->get('/p/linen-shirt')->assertOk()->assertDontSee('R 999')->assertDontSee('R 1 500 ')->assertDontSee('"price"', false);
        $this->get('/search?q=linen')->assertOk()->assertSee('Linen Shirt')->assertDontSee('R 999')->assertDontSee('Price: low');
        $this->getJson('/search/suggest?q=linen')->assertOk()->assertJsonMissingPath('results.0.price')->assertJsonPath('results.0.colour', 'Black');

        $this->post('/wishlist/toggle', ['product_id' => $product->id, 'colour_id' => $product->colours->first()->id]);
        $this->get('/wishlist')->assertOk()->assertSee('Ask for price')->assertDontSee('R 999');
    }

    public function test_price_sorting_is_ignored(): void
    {
        $this->product();
        $this->get('/search?q=linen&sort=price-asc')->assertOk()->assertSee('Linen Shirt');
    }

    public function test_customer_can_ask_for_price_with_colour_and_size(): void
    {
        $product = $this->product();
        $colour = $product->colours->first();
        $variant = $colour->variants->first();

        $this->postJson('/enquiry', [
            'product_id' => $product->id, 'colour_id' => $colour->id, 'variant_id' => $variant->id,
            'name' => 'Nadia', 'phone' => '+27 82 123 4567', 'message' => 'Is it lined?',
        ])->assertOk()->assertJson(['ok' => true]);

        $enquiry = PriceEnquiry::sole();
        $this->assertSame('Linen Shirt', $enquiry->product_name);
        $this->assertSame('Black', $enquiry->colour);
        $this->assertSame('M', $enquiry->size);
        $this->assertSame('S00001-900-M', $enquiry->sku);
        $this->assertNull($enquiry->email);
        $this->assertSame('new', $enquiry->status);
    }

    public function test_shop_is_emailed_about_each_request(): void
    {
        \Illuminate\Support\Facades\Mail::fake();
        \App\Models\Setting::saveMany(['contact_email' => 'owner@shop.test']);
        $product = $this->product();

        $this->postJson('/enquiry', ['product_id' => $product->id, 'name' => 'Nadia', 'phone' => '+27 82 123 4567', 'email' => 'nadia@mail.test', 'message' => 'Is it lined?'])
            ->assertOk();

        \Illuminate\Support\Facades\Mail::assertSent(\App\Mail\NewPriceEnquiry::class, function ($mail) {
            $html = $mail->render();

            return $mail->hasTo('owner@shop.test')
                && $mail->hasReplyTo('nadia@mail.test')
                && str_contains($mail->envelope()->subject, 'Linen Shirt')
                && str_contains($html, 'tel:+27821234567')
                && str_contains($html, 'Is it lined?')
                && str_contains($html, '/admin/price-enquiries');
        });
    }

    public function test_request_is_saved_even_if_the_email_fails(): void
    {
        \App\Models\Setting::saveMany(['contact_email' => 'owner@shop.test']);
        \Illuminate\Support\Facades\Mail::shouldReceive('to')->andThrow(new \RuntimeException('SMTP down'));
        $product = $this->product();

        $this->postJson('/enquiry', ['product_id' => $product->id, 'name' => 'Nadia', 'phone' => '0821234567'])->assertOk();
        $this->assertSame(1, PriceEnquiry::count());
    }

    public function test_name_and_valid_phone_are_required_email_is_optional_but_checked(): void
    {
        $product = $this->product();

        $this->postJson('/enquiry', ['product_id' => $product->id])
            ->assertStatus(422)->assertJsonValidationErrors(['name', 'phone']);
        $this->postJson('/enquiry', ['product_id' => $product->id, 'name' => 'A', 'phone' => 'call me', 'email' => 'nope'])
            ->assertStatus(422)->assertJsonValidationErrors(['phone', 'email']);
        $this->assertSame(0, PriceEnquiry::count());
    }

    public function test_hidden_products_and_options_from_other_products_are_rejected(): void
    {
        $draft = $this->product(['status' => 'draft']);
        $this->postJson('/enquiry', ['product_id' => $draft->id, 'name' => 'A', 'phone' => '0821234567'])->assertNotFound();

        $other = $this->product(['slug' => 'other', 'style_code' => 'S2', 'status' => 'active']);
        $draftVariant = $draft->colours->first()->variants->first();
        $this->postJson('/enquiry', ['product_id' => $other->id, 'variant_id' => $draftVariant->id, 'name' => 'A', 'phone' => '0821234567'])->assertOk();

        $enquiry = PriceEnquiry::sole();
        $this->assertSame($other->id, $enquiry->product_id);
        $this->assertNull($enquiry->product_variant_id);
    }

    public function test_bots_filling_the_hidden_field_are_ignored(): void
    {
        $product = $this->product();
        $this->postJson('/enquiry', ['product_id' => $product->id, 'name' => 'Bot', 'phone' => '0821234567', 'website' => 'spam.example'])
            ->assertOk();
        $this->assertSame(0, PriceEnquiry::count());
    }

    public function test_too_many_requests_are_limited(): void
    {
        $product = $this->product();
        foreach (range(1, 5) as $i) {
            $this->postJson('/enquiry', ['product_id' => $product->id, 'name' => 'A', 'phone' => '0821234567'])->assertOk();
        }
        $this->postJson('/enquiry', ['product_id' => $product->id, 'name' => 'A', 'phone' => '0821234567'])->assertStatus(429);
    }

    /* ───── Catalogue mode: bag, checkout and orders are off ───── */

    public function test_bag_checkout_and_order_pages_are_switched_off(): void
    {
        $product = $this->product();
        $variant = $product->colours->first()->variants->first();

        $this->get('/')->assertOk()->assertDontSee('>Bag <', false)->assertSee(route('wishlist'), false);
        foreach (['/bag', '/checkout', '/order/OMR-100001/abc'] as $url) {
            $this->get($url)->assertRedirect(route('shop.home'));
        }
        $this->postJson('/bag', ['variant_id' => $variant->id, 'quantity' => 1])->assertNotFound();
        $this->assertSame(0, \App\Models\CartItem::count());

        $customer = User::factory()->create(['role' => 'customer']);
        $this->actingAs($customer)->get('/account')->assertOk()->assertDontSee('Orders')->assertDontSee('Default address');
        $this->get('/account/orders')->assertRedirect(route('shop.home'));
        $this->get('/account/addresses')->assertRedirect(route('shop.home'));
    }

    public function test_admin_sales_pages_are_hidden_and_price_is_optional(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $this->get('/admin')->assertOk()->assertDontSee('Discount codes')->assertDontSee('Delivery options');
        $this->get('/admin/orders')->assertForbidden();
        $this->get('/admin/discount-codes')->assertForbidden();
        $this->get('/admin/shipping-methods')->assertForbidden();
        $this->get('/admin/manage-settings')->assertOk()->assertDontSee('Payment instructions');

        Livewire::test(\App\Filament\Resources\Products\Pages\CreateProduct::class)
            ->fillForm(['name' => 'No Price Dress', 'slug' => 'no-price-dress', 'style_code' => 'D00001', 'status' => 'active', 'price' => null])
            ->call('create')
            ->assertHasNoFormErrors(['price']);
    }

    /* ───── Admin ───── */

    public function test_staff_see_enquiries_with_new_count_in_menu(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'staff']));
        $new = $this->enquiry();
        $closed = $this->enquiry(['name' => 'Old Lead', 'status' => 'closed']);

        $this->get('/admin/price-enquiries')->assertOk()->assertSee('Nadia Customer')->assertSee('Black · M');
        $this->assertSame('1', \App\Filament\Resources\PriceEnquiries\PriceEnquiryResource::getNavigationBadge());

        Livewire::test(ListPriceEnquiries::class)
            ->filterTable('status', 'new')
            ->assertCanSeeTableRecords([$new])
            ->assertCanNotSeeTableRecords([$closed]);
    }

    public function test_admin_can_mark_contacted_and_add_a_note(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $enquiry = $this->enquiry();

        Livewire::test(ListPriceEnquiries::class)
            ->callTableAction('markContacted', $enquiry);
        $this->assertSame('contacted', $enquiry->fresh()->status);

        Livewire::test(ListPriceEnquiries::class)
            ->callTableAction('edit', $enquiry, ['status' => 'closed', 'admin_note' => 'Bought in store'])
            ->assertHasNoTableActionErrors();
        $this->assertSame('closed', $enquiry->fresh()->status);
        $this->assertSame('Bought in store', $enquiry->fresh()->admin_note);
    }

    public function test_customers_cannot_open_the_admin_list(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'customer']));
        $this->get('/admin/price-enquiries')->assertForbidden();
    }
}

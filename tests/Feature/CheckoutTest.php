<?php

namespace Tests\Feature;

use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Mail\NewOrderForShop;
use App\Mail\OrderPlaced;
use App\Mail\OrderStatusChanged;
use App\Models\DiscountCode;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\ShippingSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    private ProductVariant $medium;

    private ProductVariant $large;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Mail::fake();
        $this->seed(ShippingSeeder::class); // ZA standard R100 (free ≥ R1 500), express R180, international R650
        Setting::saveMany(['contact_email' => 'shop@example.com']);

        $product = Product::create(['name' => 'Wool Coat', 'slug' => 'wool-coat', 'style_code' => 'WC1', 'price' => 115000, 'status' => 'active']); // R1 150 incl. VAT
        $colour = $product->colours()->create(['name' => 'Camel', 'code' => '200']);
        $this->medium = $colour->variants()->create(['size' => 'M', 'stock' => 3]);
        $this->large = $colour->variants()->create(['size' => 'L', 'stock' => 1]);
    }

    private function address(array $override = []): array
    {
        return $override + [
            'email' => 'Thandi@Example.com', 'phone' => '+27 82 000 0000',
            'first_name' => 'Thandi', 'last_name' => 'Nkosi', 'line1' => '12 Long Street',
            'city' => 'Cape Town', 'province' => 'Western Cape', 'postal_code' => '8001', 'country' => 'ZA',
            'shipping_method_id' => 1, 'terms' => '1',
        ];
    }

    private function addToBag(ProductVariant $variant, int $qty = 1)
    {
        return $this->postJson('/bag', ['variant_id' => $variant->id, 'quantity' => $qty]);
    }

    /* ───── Bag ───── */

    public function test_add_to_bag_is_limited_by_stock(): void
    {
        $this->addToBag($this->large)->assertOk()->assertJson(['ok' => true, 'count' => 1]);
        $this->addToBag($this->large)->assertStatus(422)->assertJson(['ok' => false, 'count' => 1]); // only 1 in stock

        $this->get('/bag')->assertOk()->assertSee('Wool Coat')->assertSee('Only 1 left')->assertSee('R 1 150');
    }

    public function test_sold_out_or_hidden_products_cannot_be_added(): void
    {
        $this->large->update(['stock' => 0]);
        $this->addToBag($this->large)->assertStatus(422);

        Product::query()->update(['status' => 'draft']);
        $this->addToBag($this->medium)->assertNotFound();
    }

    public function test_change_quantity_and_remove(): void
    {
        $this->addToBag($this->medium);
        $item = \App\Models\CartItem::sole();

        $this->patch("/bag/{$item->id}", ['quantity' => 5]);   // capped at stock 3
        $this->assertSame(3, $item->fresh()->quantity);

        $this->delete("/bag/{$item->id}");
        $this->assertNull($item->fresh());
    }

    public function test_discount_codes(): void
    {
        DiscountCode::create(['code' => 'save10', 'type' => 'percent', 'value' => 10]);
        DiscountCode::create(['code' => 'BIGSPEND', 'type' => 'fixed', 'value' => 50000, 'min_subtotal' => 500000]);
        DiscountCode::create(['code' => 'OLD', 'type' => 'percent', 'value' => 50, 'ends_at' => now()->subDay()]);
        $this->addToBag($this->medium);

        $this->post('/bag/discount', ['code' => 'nope'])->assertSessionHasErrors('code');
        $this->post('/bag/discount', ['code' => 'OLD'])->assertSessionHasErrors(['code' => 'This code has expired.']);
        $this->post('/bag/discount', ['code' => 'BIGSPEND'])->assertSessionHasErrors('code');
        $this->post('/bag/discount', ['code' => 'Save10'])->assertSessionHas('status');

        $this->get('/bag')->assertSee('Discount (SAVE10)')->assertSee('−R 115');
    }

    /* ───── Checkout ───── */

    public function test_guest_checkout_in_south_africa(): void
    {
        $this->addToBag($this->medium, 2);   // 2 × R1 150 = R2 300 → free standard delivery

        $this->get('/checkout')->assertOk()->assertSee('Standard delivery')->assertSee('Place order');

        $response = $this->post('/checkout', $this->address(['gift_wrap' => '1', 'gift_message' => 'Happy birthday']));
        $order = Order::with('items')->sole();
        $response->assertRedirect($order->confirmationUrl());

        $this->assertMatchesRegularExpression('/^FS-1\d{5}$/', $order->number);
        $this->assertSame('awaiting_payment', $order->status);
        $this->assertSame('thandi@example.com', $order->email);
        $this->assertSame([230000, 0, 230000], [$order->subtotal, $order->shipping_total, $order->total]);
        $this->assertSame(30000, $order->vat_total);        // 2 300 × 15/115
        $this->assertFalse($order->vat_exempt);
        $this->assertTrue($order->gift_wrap);
        $this->assertSame(['Wool Coat', 'Camel', 'M', 2, 115000], [$order->items[0]->name, $order->items[0]->colour, $order->items[0]->size, $order->items[0]->quantity, $order->items[0]->unit_price]);

        $this->assertSame(1, $this->medium->fresh()->stock, 'stock is reserved');
        $this->assertSame(0, \App\Models\CartItem::count(), 'bag is emptied');

        Mail::assertSent(OrderPlaced::class, fn ($m) => $m->hasTo('thandi@example.com'));
        Mail::assertSent(NewOrderForShop::class, fn ($m) => $m->hasTo('shop@example.com'));

        $this->get($order->confirmationUrl())->assertOk()
            ->assertSee('Your order has been received')
            ->assertSee($order->number)
            ->assertSee('bank transfer');
    }

    public function test_international_order_has_vat_removed_and_international_delivery(): void
    {
        $this->addToBag($this->medium);

        $this->getJson('/checkout/quote?country=GB')->assertOk()
            ->assertJsonPath('methods.0.name', 'International delivery')
            ->assertJsonCount(1, 'methods');

        $this->post('/checkout', $this->address(['country' => 'GB', 'province' => 'Greater London', 'postal_code' => 'SW1A 1AA', 'shipping_method_id' => 3]));

        $order = Order::sole();
        $this->assertTrue($order->vat_exempt);
        $this->assertSame(100000, $order->subtotal);      // R1 150 / 1.15 = R1 000
        $this->assertSame(65000, $order->shipping_total);
        $this->assertSame(165000, $order->total);
        $this->assertSame(0, $order->vat_total);
    }

    public function test_delivery_must_match_the_country(): void
    {
        $this->addToBag($this->medium);

        $this->post('/checkout', $this->address(['country' => 'GB', 'province' => 'X', 'shipping_method_id' => 1]))
            ->assertSessionHasErrors('shipping_method_id');
        $this->assertSame(0, Order::count());
    }

    public function test_paid_delivery_below_free_threshold_and_discount_is_applied_and_counted(): void
    {
        DiscountCode::create(['code' => 'TEN', 'type' => 'percent', 'value' => 10, 'max_uses' => 1]);
        $this->addToBag($this->medium);
        $this->post('/bag/discount', ['code' => 'TEN']);

        $this->post('/checkout', $this->address());

        $order = Order::sole();
        $this->assertSame([115000, 11500, 10000, 113500], [$order->subtotal, $order->discount_total, $order->shipping_total, $order->total]);
        $this->assertSame(1, DiscountCode::first()->used_count);

        // Used up now.
        $this->addToBag($this->medium);
        $this->post('/bag/discount', ['code' => 'TEN'])->assertSessionHasErrors(['code' => 'This code has been used up.']);
    }

    public function test_checkout_validation(): void
    {
        $this->addToBag($this->medium);

        $this->post('/checkout', ['country' => 'ZA'])->assertSessionHasErrors(['email', 'phone', 'first_name', 'line1', 'city', 'province', 'postal_code', 'terms', 'shipping_method_id']);
        $this->post('/checkout', $this->address(['province' => 'Narnia']))->assertSessionHasErrors('province');
        $this->assertSame(0, Order::count());
    }

    public function test_cannot_buy_more_than_is_left(): void
    {
        $this->addToBag($this->large);
        $this->large->update(['stock' => 0]);   // someone else bought the last one

        $this->post('/checkout', $this->address())->assertSessionHasErrors('bag');
        $this->assertSame(0, Order::count());
        $this->assertSame(0, $this->large->fresh()->stock);
    }

    public function test_empty_bag_redirects(): void
    {
        $this->get('/checkout')->assertRedirect('/bag');
        $this->post('/checkout', $this->address())->assertRedirect('/bag');
    }

    public function test_order_page_needs_the_secret_link(): void
    {
        $this->addToBag($this->medium);
        $this->post('/checkout', $this->address());
        $order = Order::sole();

        $this->get("/order/{$order->number}/wrong-token")->assertNotFound();
        $this->get($order->confirmationUrl())->assertOk();
    }

    /* ───── Accounts ───── */

    public function test_guest_bag_moves_into_account_and_orders_show_in_my_account(): void
    {
        $user = User::factory()->create(['role' => 'customer', 'email' => 'me@example.com', 'password' => 'secret12345']);
        $this->addToBag($this->medium);

        $this->post('/login', ['email' => 'me@example.com', 'password' => 'secret12345']);
        $this->get('/bag')->assertSee('Wool Coat');

        $this->post('/checkout', $this->address(['save_address' => '1']));
        $order = Order::sole();
        $this->assertSame($user->id, $order->user_id);
        $this->assertSame('12 Long Street', $user->addresses()->sole()->line1, 'address saved to account');

        $this->get('/account/orders')->assertOk()->assertSee($order->number)->assertSee('Awaiting payment');
        $this->get('/account/orders/'.$order->number)->assertRedirect($order->confirmationUrl());

        // Another customer can't open it via My orders.
        $this->actingAs(User::factory()->create(['role' => 'customer']));
        $this->get('/account/orders/'.$order->number)->assertNotFound();
    }

    /* ───── Admin ───── */

    private function placeOrder(): Order
    {
        $this->addToBag($this->medium, 2);
        $this->post('/checkout', $this->address());

        return Order::sole();
    }

    public function test_admin_marks_paid_shipped_and_delivered(): void
    {
        $order = $this->placeOrder();
        $this->actingAs(User::factory()->create(['role' => 'staff']));
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->get('/admin/orders')->assertOk();
        $this->get("/admin/orders/{$order->id}")->assertOk()->assertSee($order->number);

        Livewire::test(ViewOrder::class, ['record' => $order->id])->callAction('markPaid');
        $this->assertSame('paid', $order->fresh()->status);
        Mail::assertSent(OrderStatusChanged::class, fn ($m) => $m->event === 'paid');

        Livewire::test(ViewOrder::class, ['record' => $order->id])
            ->callAction('ship', ['courier' => 'The Courier Guy', 'tracking_number' => 'TCG123', 'tracking_url' => 'https://example.com/t/TCG123']);
        $order->refresh();
        $this->assertSame(['shipped', 'TCG123'], [$order->status, $order->tracking_number]);
        Mail::assertSent(OrderStatusChanged::class, fn ($m) => $m->event === 'shipped');

        Livewire::test(ViewOrder::class, ['record' => $order->id])->callAction('delivered');
        $this->assertSame('delivered', $order->fresh()->status);
    }

    public function test_admin_cancel_returns_stock(): void
    {
        $order = $this->placeOrder();
        $this->assertSame(1, $this->medium->fresh()->stock);
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(ViewOrder::class, ['record' => $order->id])->callAction('cancel', ['notify' => true]);

        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame(3, $this->medium->fresh()->stock);
        Mail::assertSent(OrderStatusChanged::class, fn ($m) => $m->event === 'cancelled');
    }

    public function test_order_emails_render(): void
    {
        $order = $this->placeOrder();
        Setting::saveMany(['payment_instructions' => "Bank: Example Bank\nAccount: 123"]);

        $html = (new OrderPlaced($order))->render();
        $this->assertStringContainsString($order->number, $html);
        $this->assertStringContainsString('Example Bank', $html);
        $this->assertStringContainsString('R 2 300', $html);

        foreach (['paid', 'shipped', 'cancelled'] as $event) {
            $this->assertStringContainsString($order->number, (new OrderStatusChanged($order, $event))->render());
        }
        $this->assertStringContainsString('Thandi Nkosi', (new NewOrderForShop($order))->render());
    }
}

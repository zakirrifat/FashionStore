<?php

namespace Tests\Feature;

use App\Filament\Resources\ContactMessages\Pages\ListContactMessages;
use App\Filament\Resources\Customers\Pages\EditCustomer;
use App\Livewire\AdminNotificationBell;
use App\Models\ContactMessage;
use App\Models\Order;
use App\Models\PriceEnquiry;
use App\Models\Product;
use App\Models\ProductColour;
use App\Models\User;
use App\Services\AdminNotifier;
use App\Support\Wishlist;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdminNotificationsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $staff;

    private Product $product;

    private ProductColour $colour;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Mail::fake();
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->staff = User::factory()->create(['role' => 'staff']);
        $this->product = Product::create(['name' => 'Wool Coat', 'slug' => 'wool-coat', 'style_code' => 'WC1', 'price' => 115000, 'status' => 'active']);
        $this->colour = $this->product->colours()->create(['name' => 'Camel', 'code' => '200']);
    }

    private function enquiry(): PriceEnquiry
    {
        $this->postJson('/enquiry', ['product_id' => $this->product->id, 'name' => '<b>Thandi</b>', 'phone' => '+27 82 000 0000'])->assertOk();

        return PriceEnquiry::firstOrFail();
    }

    public function test_bell_is_shown_in_admin(): void
    {
        $this->actingAs($this->admin)->get('/admin')->assertOk()->assertSeeLivewire(AdminNotificationBell::class);
    }

    public function test_bell_changes_colour_only_while_there_are_unread_alerts(): void
    {
        $this->actingAs($this->admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(AdminNotificationBell::class)->assertDontSeeHtml('admin-bell-unread');

        $enquiry = $this->enquiry();
        Livewire::test(AdminNotificationBell::class)->assertSeeHtml('admin-bell-unread');

        $enquiry->update(['status' => 'contacted']);
        Livewire::test(AdminNotificationBell::class)->assertDontSeeHtml('admin-bell-unread');
    }

    public function test_open_button_is_a_real_post_form(): void
    {
        $this->enquiry();
        $this->actingAs($this->admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $html = Notification::fromDatabase($this->admin->notifications()->first())->toHtml();

        $this->assertMatchesRegularExpression('/<form[^>]+method="post"/', $html);
        $this->assertStringContainsString('name="_token"', $html);
        $this->assertMatchesRegularExpression('/<form.*?<button[^>]*type="submit"/s', $html);
    }

    /** @return array<string, array{string}> */
    public static function alertTypes(): array
    {
        return ['enquiry' => ['enquiry'], 'order' => ['order'], 'message' => ['message'], 'wishlist' => ['wishlist']];
    }

    #[DataProvider('alertTypes')]
    public function test_opening_any_alert_clears_it_for_the_team_and_goes_to_the_item(string $type): void
    {
        [$key, $target] = match ($type) {
            'enquiry' => (function () {
                $e = $this->enquiry();

                return ['enquiry:'.$e->id, '/admin/price-enquiries?tableAction=view&tableActionRecord='.$e->id];
            })(),
            'order' => (function () {
                $o = Order::create([
                    'number' => 'FS-100002', 'access_token' => str_repeat('b', 48), 'status' => 'awaiting_payment',
                    'email' => 'a@example.com', 'phone' => '0820000000', 'shipping_address' => ['first_name' => 'Thandi'],
                    'shipping_method' => 'Standard', 'currency' => 'ZAR', 'subtotal' => 1000, 'discount_total' => 0,
                    'shipping_total' => 0, 'gift_wrap_total' => 0, 'total' => 1000, 'vat_total' => 0,
                ]);
                AdminNotifier::order($o);

                return ['order:'.$o->id, '/admin/orders/'.$o->id];
            })(),
            'message' => (function () {
                $m = ContactMessage::create(['name' => 'Thandi', 'email' => 'a@example.com', 'subject' => 'Other', 'message' => 'Hello there, a question.']);
                AdminNotifier::contactMessage($m);

                return ['message:'.$m->id, '/admin/contact-messages?tableAction=view&tableActionRecord='.$m->id];
            })(),
            'wishlist' => (function () {
                $c = User::factory()->create(['role' => 'customer']);
                $this->actingAs($c);
                Wishlist::toggle($this->product, $this->colour);

                return [$this->admin->notifications()->first()->data['admin_key'], '/admin/customers/'.$c->id.'/edit?relation=1'];
            })(),
        };
        $this->assertSame(2, DatabaseNotification::count());

        $response = $this->actingAs($this->staff)->withoutMiddleware(ValidateCsrfToken::class)
            ->post('/admin/alerts/open?key='.urlencode($key));

        $this->assertSame(url($target), urldecode($response->headers->get('Location')));
        $this->assertSame(0, DatabaseNotification::count());
    }

    public function test_open_rejects_customers_and_bad_keys(): void
    {
        $this->enquiry();
        $customer = User::factory()->create(['role' => 'customer']);
        $key = $this->admin->notifications()->first()->data['admin_key'];

        $this->actingAs($customer)->withoutMiddleware(ValidateCsrfToken::class)
            ->post('/admin/alerts/open?key='.urlencode($key))->assertForbidden();
        $this->assertSame(2, DatabaseNotification::count());

        foreach (['enquiry:%', 'order:1:2026-01-01', 'wishlist:1', 'https://evil.example'] as $bad) {
            $this->actingAs($this->admin)->withoutMiddleware(ValidateCsrfToken::class)
                ->post('/admin/alerts/open?key='.urlencode($bad))->assertNotFound();
        }
        $this->assertSame(2, DatabaseNotification::count());
    }

    public function test_enquiry_alerts_the_team_without_a_queue_worker_and_escapes_customer_text(): void
    {
        Queue::fake();
        $customer = User::factory()->create(['role' => 'customer']);

        $this->enquiry();

        Queue::assertNothingPushed();
        $this->assertSame(1, $this->admin->unreadNotifications()->count());
        $this->assertSame(1, $this->staff->unreadNotifications()->count());
        $this->assertSame(0, $customer->notifications()->count());
        $this->assertStringContainsString('&lt;b&gt;Thandi', $this->admin->notifications()->first()->data['title']);
    }

    public function test_handling_an_enquiry_clears_it_for_everyone(): void
    {
        $enquiry = $this->enquiry();

        $enquiry->update(['status' => 'contacted']);

        $this->assertSame(0, DatabaseNotification::whereNull('read_at')->count());
        $this->assertSame(2, DatabaseNotification::count());
    }

    public function test_deleting_an_enquiry_removes_its_alert(): void
    {
        $this->enquiry()->delete();

        $this->assertSame(0, DatabaseNotification::count());
    }

    public function test_order_alert_clears_when_paid(): void
    {
        $order = Order::create([
            'number' => 'FS-100001', 'access_token' => str_repeat('a', 48), 'status' => 'awaiting_payment',
            'email' => 'a@example.com', 'phone' => '0820000000', 'shipping_address' => ['first_name' => 'Thandi', 'last_name' => 'Nkosi'],
            'shipping_method' => 'Standard', 'currency' => 'ZAR', 'subtotal' => 1000, 'discount_total' => 0,
            'shipping_total' => 0, 'gift_wrap_total' => 0, 'total' => 1000, 'vat_total' => 0,
        ]);
        AdminNotifier::order($order);
        $this->assertSame(2, DatabaseNotification::whereNull('read_at')->count());

        $order->update(['status' => 'paid']);

        $this->assertSame(0, DatabaseNotification::whereNull('read_at')->count());
    }

    public function test_opening_a_contact_message_from_the_alert_link_clears_it(): void
    {
        $this->post('/contact', [
            'name' => 'Thandi', 'phone' => '+27 82 123 4567', 'email' => 'a@example.com', 'subject' => 'Other', 'message' => 'Hello there, a question.',
        ])->assertRedirect();
        $message = ContactMessage::firstOrFail();
        $this->assertSame(2, DatabaseNotification::whereNull('read_at')->count());

        $this->actingAs($this->admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Livewire::withQueryParams(['tableAction' => 'view', 'tableActionRecord' => $message->id])
            ->test(ListContactMessages::class)
            ->call('mountDefaultActions'); // the browser calls this on page load

        $this->assertNotNull($message->fresh()->read_at);
        $this->assertSame(0, DatabaseNotification::whereNull('read_at')->count());
    }

    public function test_wishlist_groups_per_customer_per_day(): void
    {
        $customer = User::factory()->create(['role' => 'customer', 'marketing_opt_in' => false]);
        $second = $this->product->colours()->create(['name' => 'Navy', 'code' => '300']);
        $this->actingAs($customer);

        Wishlist::toggle($this->product, $this->colour);
        $this->admin->notifications()->update(['read_at' => now()]);
        Wishlist::toggle($this->product, $second);

        $alert = $this->admin->notifications()->sole();
        $this->assertNull($alert->read_at); // new saves bring it back
        $this->assertStringContainsString('saved 2 items today', $alert->data['title']);
        $this->assertStringContainsString('Newsletter consent: No', $alert->data['body']);

        Wishlist::toggle($this->product, $second);
        $this->assertStringContainsString('saved 1 item today', $this->admin->notifications()->sole()->data['title']);

        Wishlist::toggle($this->product, $this->colour);
        $this->assertSame(0, $this->admin->notifications()->count());
    }

    public function test_guest_wishlist_alerts_once_after_sign_in_only(): void
    {
        Wishlist::toggle($this->product, $this->colour);
        $this->assertSame(0, DatabaseNotification::count());

        $customer = User::factory()->create(['role' => 'customer']);
        Wishlist::mergeGuestInto($customer);

        $this->assertSame(1, $this->admin->notifications()->count());
    }

    public function test_staff_wishlists_do_not_alert(): void
    {
        $this->actingAs($this->staff);
        Wishlist::toggle($this->product, $this->colour);

        $this->assertSame(0, DatabaseNotification::count());
    }

    public function test_wishlist_alert_link_opens_the_customers_wishlist(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $customer->wishlistItems()->create(['product_id' => $this->product->id, 'product_colour_id' => $this->colour->id]);

        $this->actingAs($this->admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Livewire::withQueryParams(['relation' => 1])
            ->test(EditCustomer::class, ['record' => $customer->getKey()])
            ->assertSet('activeRelationManager', 1);

        // The tab's table loads lazily, so check it directly.
        Livewire::test(\App\Filament\Resources\Customers\RelationManagers\WishlistRelationManager::class, [
            'ownerRecord' => $customer,
            'pageClass' => EditCustomer::class,
        ])->assertSee('Wool Coat');
    }
}

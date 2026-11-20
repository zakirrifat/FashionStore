<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Product;
use App\Models\User;
use App\Models\WishlistItem;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AccountTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function customer(array $attrs = []): User
    {
        return User::factory()->create($attrs + ['role' => 'customer', 'password' => 'secret12345']);
    }

    private function product(): Product
    {
        $product = Product::create(['name' => 'Wool Coat', 'slug' => 'wool-coat', 'style_code' => 'W1', 'price' => 500000, 'status' => 'active']);
        $colour = $product->colours()->create(['name' => 'Camel', 'code' => '200']);
        $colour->variants()->create(['size' => 'M', 'stock' => 2]);

        return $product->load('colours');
    }

    /* ───── Register / sign in ───── */

    public function test_customer_can_register_and_cannot_choose_their_role(): void
    {
        $this->post('/register', [
            'name' => 'Lerato Mokoena',
            'email' => 'Lerato@Example.com',
            'password' => 'goodpass123',
            'password_confirmation' => 'goodpass123',
            'terms' => '1',
            'role' => 'admin', // attacker tries to become admin
        ])->assertRedirect(route('account.overview'));

        $user = User::firstWhere('email', 'lerato@example.com');
        $this->assertNotNull($user, 'email is stored lowercase');
        $this->assertSame('customer', $user->role);
        $this->assertAuthenticatedAs($user);
        $this->get('/admin')->assertForbidden();
    }

    public function test_registration_requires_terms_and_strong_password(): void
    {
        $this->post('/register', [
            'name' => 'X', 'email' => 'x@example.com', 'password' => 'short', 'password_confirmation' => 'short',
        ])->assertSessionHasErrors(['password', 'terms']);

        $this->assertGuest();
    }

    public function test_sign_in_and_out(): void
    {
        $user = $this->customer(['email' => 'sam@example.com']);

        $this->post('/login', ['email' => 'SAM@example.com', 'password' => 'wrong'])->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->post('/login', ['email' => 'sam@example.com', 'password' => 'secret12345'])->assertRedirect(route('account.overview'));
        $this->assertAuthenticatedAs($user);

        $this->post('/logout')->assertRedirect('/');
        $this->assertGuest();
    }

    public function test_sign_in_is_rate_limited(): void
    {
        $this->customer(['email' => 'sam@example.com']);

        foreach (range(1, 5) as $i) {
            $this->post('/login', ['email' => 'sam@example.com', 'password' => 'wrong']);
        }

        $this->post('/login', ['email' => 'sam@example.com', 'password' => 'secret12345'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_account_pages_need_sign_in(): void
    {
        $this->get('/account')->assertRedirect('/login');
        $this->get('/account/addresses')->assertRedirect('/login');
    }

    public function test_password_reset_flow(): void
    {
        Notification::fake();
        $user = $this->customer(['email' => 'reset@example.com']);

        $this->post('/forgot-password', ['email' => 'reset@example.com'])->assertSessionHas('status');
        // Unknown email gets the same answer.
        $this->post('/forgot-password', ['email' => 'nobody@example.com'])->assertSessionHas('status');

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
            $this->get('/reset-password/'.$notification->token.'?email='.$user->email)->assertOk();

            $this->post('/reset-password', [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'brandnew123',
                'password_confirmation' => 'brandnew123',
            ])->assertRedirect('/login');

            return true;
        });

        $this->post('/login', ['email' => 'reset@example.com', 'password' => 'brandnew123']);
        $this->assertAuthenticatedAs($user);
    }

    /* ───── Profile ───── */

    public function test_update_details_and_password(): void
    {
        $user = $this->customer(['email' => 'old@example.com']);
        $this->actingAs($user);

        // Changing email needs the current password.
        $this->put('/account/details', ['name' => 'New Name', 'email' => 'new@example.com'])
            ->assertSessionHasErrors('current_password');

        $this->put('/account/details', ['name' => 'New Name', 'email' => 'new@example.com', 'current_password' => 'secret12345', 'role' => 'admin'])
            ->assertSessionHas('status');
        $user->refresh();
        $this->assertSame(['New Name', 'new@example.com', 'customer'], [$user->name, $user->email, $user->role]);

        $this->put('/account/password', ['current_password' => 'nope', 'password' => 'another123', 'password_confirmation' => 'another123'])
            ->assertSessionHasErrorsIn('password', 'current_password');
        $this->put('/account/password', ['current_password' => 'secret12345', 'password' => 'another123', 'password_confirmation' => 'another123'])
            ->assertSessionHas('status');
    }

    /* ───── Addresses ───── */

    private function addressData(array $override = []): array
    {
        return $override + [
            'first_name' => 'Lerato', 'last_name' => 'Mokoena', 'line1' => '12 Long Street',
            'city' => 'Cape Town', 'province' => 'Western Cape', 'postal_code' => '8001',
            'country' => 'ZA', 'phone' => '+27 82 000 0000',
        ];
    }

    public function test_addresses_crud_and_single_default(): void
    {
        $user = $this->customer();
        $this->actingAs($user);

        $this->post('/account/addresses', $this->addressData())->assertRedirect('/account/addresses');
        $this->post('/account/addresses', $this->addressData(['line1' => '1 Main Road', 'is_default' => '1']));

        $addresses = $user->addresses()->get();
        $this->assertCount(2, $addresses);
        $this->assertSame(1, $addresses->where('is_default', true)->count());
        $this->assertSame('1 Main Road', $addresses->firstWhere('is_default', true)->line1);

        $this->get('/account/addresses')->assertOk()->assertSee('12 Long Street')->assertSee('South Africa');

        // Delete the default: the other becomes default.
        $this->delete('/account/addresses/'.$addresses->firstWhere('is_default', true)->id);
        $this->assertTrue($user->addresses()->sole()->is_default);
    }

    public function test_address_validation_for_south_africa_and_abroad(): void
    {
        $this->actingAs($this->customer());

        $this->post('/account/addresses', $this->addressData(['province' => 'Narnia']))->assertSessionHasErrors('province');
        $this->post('/account/addresses', $this->addressData(['country' => 'XX']))->assertSessionHasErrors('country');
        $this->post('/account/addresses', $this->addressData(['country' => 'GB', 'province' => 'Greater London', 'postal_code' => 'SW1A 1AA']))
            ->assertSessionHasNoErrors();
    }

    public function test_customer_cannot_touch_someone_elses_address(): void
    {
        $other = $this->customer();
        $address = $other->addresses()->create($this->addressData());

        $this->actingAs($this->customer());
        $this->get("/account/addresses/{$address->id}/edit")->assertNotFound();
        $this->put("/account/addresses/{$address->id}", $this->addressData(['line1' => 'Hacked']))->assertNotFound();
        $this->delete("/account/addresses/{$address->id}")->assertNotFound();
        $this->assertSame('12 Long Street', $address->fresh()->line1);
    }

    /* ───── Wishlist ───── */

    public function test_guest_wishlist_moves_into_account_on_sign_in(): void
    {
        $product = $this->product();
        $colour = $product->colours->first();
        $user = $this->customer(['email' => 'w@example.com']);

        $this->postJson('/wishlist/toggle', ['product_id' => $product->id, 'colour_id' => $colour->id])
            ->assertJson(['added' => true, 'count' => 1]);
        $this->get('/wishlist')->assertOk()->assertSee('Wool Coat')->assertSee('saved on this device');

        $this->post('/login', ['email' => 'w@example.com', 'password' => 'secret12345']);

        $this->assertDatabaseHas('wishlist_items', ['user_id' => $user->id, 'product_id' => $product->id, 'product_colour_id' => $colour->id]);
        $this->get('/wishlist')->assertSee('Wool Coat')->assertDontSee('saved on this device');
    }

    public function test_signed_in_wishlist_toggle_and_remove(): void
    {
        $product = $this->product();
        $colour = $product->colours->first();
        $this->actingAs($this->customer());

        $this->postJson('/wishlist/toggle', ['product_id' => $product->id, 'colour_id' => $colour->id])->assertJson(['added' => true]);
        $this->get('/p/wool-coat')->assertSee('In your wishlist');

        $this->post('/wishlist/toggle', ['product_id' => $product->id, 'colour_id' => $colour->id])->assertRedirect();
        $this->assertSame(0, WishlistItem::count());
    }

    public function test_hidden_products_cannot_be_wishlisted(): void
    {
        $product = $this->product();
        $product->update(['status' => 'draft']);

        $this->postJson('/wishlist/toggle', ['product_id' => $product->id])->assertNotFound();
    }
}

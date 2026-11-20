<?php

namespace Tests\Feature;

use App\Filament\Resources\Customers\Pages\EditCustomer;
use App\Filament\Resources\Customers\Pages\ListCustomers;
use App\Models\Category;
use App\Models\Collection;
use App\Models\Product;
use App\Models\SizeGuide;
use App\Models\User;
use App\Services\ProductCsv;
use App\Support\Csv;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class AdminToolsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    /* ───── Customers & team ───── */

    public function test_customers_and_team_are_separate_lists(): void
    {
        $this->actingAs($this->admin());
        $customer = User::factory()->create(['role' => 'customer', 'name' => 'Cara Customer']);
        $staff = User::factory()->create(['role' => 'staff', 'name' => 'Sam Staff']);

        Livewire::test(ListCustomers::class)->assertCanSeeTableRecords([$customer])->assertCanNotSeeTableRecords([$staff]);
        $this->get('/admin/users')->assertOk()->assertSee('Sam Staff')->assertDontSee('Cara Customer');
        $this->get("/admin/customers/{$customer->id}/edit")->assertOk();
        $this->get("/admin/customers/{$staff->id}/edit")->assertNotFound();
    }

    public function test_staff_can_edit_customers_but_not_change_their_role(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'staff']));
        $customer = User::factory()->create(['role' => 'customer']);
        $customer->addresses()->create(['first_name' => 'A', 'last_name' => 'B', 'line1' => '1 St', 'city' => 'Durban', 'province' => 'KwaZulu-Natal', 'postal_code' => '4001', 'country' => 'ZA', 'phone' => '1', 'is_default' => true]);

        Livewire::test(EditCustomer::class, ['record' => $customer->getRouteKey()])
            ->assertFormFieldIsHidden('role')
            ->fillForm(['phone' => '+27 11 111 1111'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('+27 11 111 1111', $customer->fresh()->phone);
        $this->assertSame('customer', $customer->fresh()->role);

        Livewire::test(\App\Filament\Resources\Customers\RelationManagers\AddressesRelationManager::class, [
            'ownerRecord' => $customer, 'pageClass' => EditCustomer::class,
        ])->assertCanSeeTableRecords($customer->addresses);

        $product = Product::create(['name' => 'Tee', 'slug' => 'tee', 'style_code' => 'T1', 'price' => 100, 'status' => 'active']);
        $item = $customer->wishlistItems()->create(['product_id' => $product->id]);
        Livewire::test(\App\Filament\Resources\Customers\RelationManagers\WishlistRelationManager::class, [
            'ownerRecord' => $customer, 'pageClass' => EditCustomer::class,
        ])->assertCanSeeTableRecords([$item])->assertSee('Tee');
    }

    public function test_customer_csv_export(): void
    {
        $this->actingAs($this->admin());
        User::factory()->create(['role' => 'customer', 'name' => '=HYPERLINK("evil")', 'email' => 'x@example.com']);

        $response = Livewire::test(ListCustomers::class)->callAction('export');
        $response->assertFileDownloaded('customers-'.now()->format('Y-m-d').'.csv');
    }

    public function test_csv_cells_are_protected_against_formulas(): void
    {
        $this->assertSame("'=HYPERLINK(\"x\")", Csv::safe('=HYPERLINK("x")'));
        $this->assertSame('-5', Csv::safe('-5'));
        $this->assertSame('Normal', Csv::safe('Normal'));
    }

    /* ───── Dashboard ───── */

    public function test_dashboard_shows_store_widgets(): void
    {
        $this->actingAs($this->admin());
        $product = Product::create(['name' => 'Tee', 'slug' => 'tee', 'style_code' => 'T1', 'price' => 50000, 'status' => 'active']);
        $product->colours()->create(['name' => 'White', 'code' => '100'])->variants()->create(['size' => 'S', 'stock' => 0]);

        $this->get('/admin')->assertOk();

        Livewire::test(\App\Filament\Widgets\StoreStats::class)
            ->assertSee('Live products')
            ->assertSee('1 sold out');
        Livewire::test(\App\Filament\Widgets\LowStock::class)
            ->assertCanSeeTableRecords(\App\Models\ProductVariant::all());
        Livewire::test(\App\Filament\Widgets\NewCustomers::class)->assertOk();
        Livewire::test(\App\Filament\Widgets\MostWishlisted::class)->assertOk();
    }

    /* ───── Size guides ───── */

    public function test_product_uses_own_then_category_size_guide(): void
    {
        $woman = Category::create(['name' => 'Woman', 'slug' => 'woman']);
        $jeans = Category::create(['name' => 'Jeans', 'slug' => 'woman-jeans', 'parent_id' => $woman->id]);
        $denimGuide = SizeGuide::create(['name' => 'Denim sizes', 'body' => '<table><tr><td>W27</td></tr></table>']);
        $woman->update(['size_guide_id' => $denimGuide->id]);

        $product = Product::create(['name' => 'Jean', 'slug' => 'jean', 'style_code' => 'J9', 'price' => 100, 'status' => 'active']);
        $product->categories()->attach($jeans);
        $product->colours()->create(['name' => 'Blue', 'code' => '1'])->variants()->create(['size' => '27', 'stock' => 1]);

        $this->get('/p/jean')->assertOk()->assertSee('Size guide — Denim sizes')->assertSee('W27');

        $own = SizeGuide::create(['name' => 'Special fit', 'body' => '<p>Runs small</p>']);
        $product->update(['size_guide_id' => $own->id]);
        $this->get('/p/jean')->assertSee('Special fit')->assertSee('Runs small')->assertDontSee('W27');
    }

    /* ───── CSV import / export ───── */

    private function rows(array $rows): array
    {
        return array_map(fn ($r) => array_combine(array_slice(ProductCsv::COLUMNS, 0, count($r)), $r) ?: $r, $rows);
    }

    public function test_import_creates_products_colours_and_sizes(): void
    {
        Category::create(['name' => 'Jeans', 'slug' => 'woman-jeans']);
        Collection::create(['name' => 'New', 'slug' => 'new-arrivals']);

        $result = ProductCsv::import(array_map(
            fn ($r) => array_combine(ProductCsv::COLUMNS, $r),
            ProductCsv::templateRows(),
        ));

        // Template refers to man-jeans too, which doesn't exist here → nothing saved.
        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('unknown categories: man-jeans', implode(' ', $result['errors']));
        $this->assertSame(0, Product::count());

        Category::create(['name' => 'Jeans', 'slug' => 'man-jeans']);
        $result = ProductCsv::import(array_map(fn ($r) => array_combine(ProductCsv::COLUMNS, $r), ProductCsv::templateRows()));
        $this->assertTrue($result['ok'], implode("\n", $result['errors']));

        $p = Product::with('colours.variants', 'categories', 'collections')->sole();
        $this->assertSame(['Loose fit jeans - 1981', 620000, 'draft', 'loose-fit-jeans-1981'], [$p->name, $p->price, $p->status, $p->slug]);
        $this->assertCount(2, $p->categories);
        $this->assertSame(['Black', 'Mid Blue'], $p->colours->pluck('name')->all());
        $this->assertSame(16, $p->totalStock());
        $this->assertSame('C00081-900-S-REG', $p->colours->first()->variants->first()->sku);
        $this->assertSame(['products_created' => 1, 'products_updated' => 0, 'sizes_created' => 3, 'sizes_updated' => 0], $result['summary']);
    }

    public function test_stock_only_import_updates_stock_and_nothing_else(): void
    {
        $p = Product::create(['name' => 'Tee', 'slug' => 'tee', 'style_code' => 'T1', 'price' => 50000, 'status' => 'active']);
        $p->colours()->create(['name' => 'White', 'code' => '100'])->variants()->create(['size' => 'M', 'stock' => 1]);

        $result = ProductCsv::import([['style_code' => 'T1', 'colour_code' => '100', 'size' => 'm', 'stock' => '25']]);

        $this->assertTrue($result['ok'], implode("\n", $result['errors']));
        $this->assertSame(25, $p->fresh()->totalStock());
        $this->assertSame(['Tee', 50000, 'active'], [$p->fresh()->name, $p->fresh()->price, $p->fresh()->status]);
        $this->assertSame(1, $result['summary']['sizes_updated']);
    }

    public function test_import_reports_all_problems_and_saves_nothing(): void
    {
        $result = ProductCsv::import([
            ['style_code' => 'N1', 'colour_code' => '1', 'size' => 'S', 'price' => 'abc', 'stock' => '-2'],
            ['style_code' => 'N1', 'colour_code' => '1', 'size' => 's', 'status' => 'live'],
            ['style_code' => '', 'colour_code' => '1', 'size' => 'M'],
        ]);

        $this->assertFalse($result['ok']);
        $text = implode("\n", $result['errors']);
        foreach (['Row 2: price', 'Row 2: stock', 'Row 3: status', 'Row 3: same colour/size', 'Row 4: style_code is empty', 'N1: new product needs a name'] as $expected) {
            $this->assertStringContainsString($expected, $text);
        }
        $this->assertSame(0, Product::count());
    }

    public function test_export_then_reimport_round_trips(): void
    {
        $p = Product::create(['name' => 'Coat', 'slug' => 'coat', 'style_code' => 'C1', 'price' => 125050, 'status' => 'active', 'details' => "Line 1\nLine 2"]);
        $p->colours()->create(['name' => 'Camel', 'code' => '200'])->variants()->create(['size' => 'L', 'length' => 'Long', 'stock' => 4, 'sku' => 'C1-CAMEL-L']);

        $rows = iterator_to_array(ProductCsv::exportRows(), false);
        $this->assertSame('1250.5', $rows[0][5]);

        $result = ProductCsv::import(array_map(fn ($r) => array_combine(ProductCsv::COLUMNS, array_map('strval', $r)), $rows));
        $this->assertTrue($result['ok'], implode("\n", $result['errors']));
        $this->assertSame(1, Product::count());
        $this->assertSame([125050, "Line 1\nLine 2", 4], [$p->fresh()->price, $p->fresh()->details, $p->fresh()->totalStock()]);
    }

    public function test_money_parsing(): void
    {
        $this->assertSame(125000, ProductCsv::cents('1250'));
        $this->assertSame(125050, ProductCsv::cents('R 1 250.50'));
        $this->assertSame(125050, ProductCsv::cents('1250,50'));
        $this->assertSame(125000, ProductCsv::cents('1,250'));
        $this->assertNull(ProductCsv::cents('abc'));
        $this->assertNull(ProductCsv::cents('-5'));
    }

    /* ───── Security ───── */

    public function test_activity_log_records_who_changed_what(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        $p = Product::create(['name' => 'Tee', 'slug' => 'tee', 'style_code' => 'T1', 'price' => 50000, 'status' => 'draft']);
        $p->update(['price' => 45000]);

        $log = Activity::where('subject_type', Product::class)->where('event', 'updated')->sole();
        $this->assertTrue($log->causer->is($admin));
        $this->assertSame(50000, $log->attribute_changes['old']['price']);
        $this->assertSame(45000, $log->attribute_changes['attributes']['price']);

        $this->get('/admin/activity-logs')->assertOk();
    }

    public function test_staff_cannot_see_activity_log_or_team(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'staff']));

        $this->get('/admin/activity-logs')->assertForbidden();
        $this->get('/admin/users')->assertForbidden();
        $this->get('/admin/customers')->assertOk();
        $this->get('/admin/size-guides')->assertOk();
    }

    public function test_staff_sign_in_and_failed_attempts_are_logged_but_customers_are_not(): void
    {
        $staff = User::factory()->create(['role' => 'staff', 'email' => 'staff@example.com', 'password' => 'secret12345']);
        User::factory()->create(['role' => 'customer', 'email' => 'c@example.com', 'password' => 'secret12345']);

        $this->post('/login', ['email' => 'staff@example.com', 'password' => 'wrong']);
        $this->post('/login', ['email' => 'c@example.com', 'password' => 'secret12345']);
        $this->post('/logout');
        $this->post('/login', ['email' => 'staff@example.com', 'password' => 'secret12345']);

        $this->assertSame(['Failed sign-in attempt', 'Signed in'], Activity::inLog('auth')->orderBy('id')->pluck('description')->all());
        $this->assertTrue(Activity::inLog('auth')->where('description', 'Signed in')->sole()->causer->is($staff));
    }

    public function test_customer_profile_edits_are_not_logged(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $customer->update(['name' => 'Changed']);

        $this->assertSame(0, Activity::where('subject_type', User::class)->count());
    }

    public function test_two_step_sign_in_is_required_when_enabled(): void
    {
        // The panel reads the setting when the app boots, so boot a fresh app with it switched on.
        $this->setEnv('ADMIN_REQUIRE_2FA', 'true');
        try {
            $this->refreshApplication();
            $this->artisan('migrate');
            $this->withoutVite();
            $this->actingAs(User::factory()->create(['role' => 'admin']));

            // Without an authenticator app set up, every admin page sends you to set one up.
            $this->get('/admin')->assertRedirect();
            $this->get('/admin/products')->assertRedirect();
        } finally {
            $this->setEnv('ADMIN_REQUIRE_2FA', 'false');
        }
    }

    private function setEnv(string $key, string $value): void
    {
        putenv("{$key}={$value}");
        $_ENV[$key] = $_SERVER[$key] = $value;
    }

    public function test_team_member_can_have_two_step_reset_by_admin(): void
    {
        $this->actingAs($this->admin());
        $staff = User::factory()->create(['role' => 'staff']);
        $staff->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');
        $staff->saveAppAuthenticationRecoveryCodes(['a', 'b']);

        Livewire::test(\App\Filament\Resources\Users\Pages\ListUsers::class)
            ->callTableAction('reset2fa', $staff);

        $this->assertNull($staff->fresh()->app_authentication_secret);
        $this->assertSame('Two-step sign-in reset by admin', Activity::inLog('auth')->latest('id')->first()->description);
    }
}

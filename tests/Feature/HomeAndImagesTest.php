<?php

namespace Tests\Feature;

use App\Filament\Pages\ManageHomePage;
use App\Models\Collection;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Support\Img;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class HomeAndImagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('public');
    }

    private function storeImage(string $path, int $w = 3000, int $h = 2000): string
    {
        Storage::disk('public')->put($path, UploadedFile::fake()->image('x.jpg', $w, $h)->getContent());

        return $path;
    }

    private function product(string $name, array $attrs = []): Product
    {
        $product = Product::create($attrs + ['name' => $name, 'slug' => Str::slug($name), 'style_code' => Str::upper(Str::random(6)), 'price' => 100000, 'status' => 'active']);
        $colour = $product->colours()->create(['name' => 'Black', 'code' => '900']);
        $colour->images()->create(['path' => $this->storeImage('products/'.Str::slug($name).'.jpg', 1200, 1600)]);

        return $product;
    }

    /* ───── Images ───── */

    public function test_resized_webp_is_made_on_first_request_then_served_statically(): void
    {
        $path = $this->storeImage('products/big.jpg');

        $url = Img::url($path, 800);
        $this->assertStringContainsString('/img/800/products/big.jpg', $url, 'first time: points at the resizer');

        $this->get('/img/800/products/big.jpg')->assertOk()->assertHeader('Content-Type', 'image/webp');

        $resized = '_resized/800/products/big.jpg.webp';
        Storage::disk('public')->assertExists($resized);
        [$width] = getimagesize(Storage::disk('public')->path($resized));
        $this->assertSame(800, $width);

        $this->assertStringContainsString($resized, Img::url($path, 800), 'afterwards: direct file URL');
    }

    public function test_resizer_rejects_unknown_sizes_folders_and_path_tricks(): void
    {
        $this->storeImage('products/a.jpg');
        Storage::disk('public')->put('private/secret.jpg', 'x');

        $this->get('/img/777/products/a.jpg')->assertNotFound();
        $this->get('/img/800/private/secret.jpg')->assertNotFound();
        $this->get('/img/800/products/../private/secret.jpg')->assertNotFound();
        $this->get('/img/800/products/missing.jpg')->assertNotFound();
    }

    public function test_small_images_are_not_enlarged(): void
    {
        $path = $this->storeImage('products/small.jpg', 300, 400);

        Img::make($path, 1600);
        [$width] = getimagesize(Storage::disk('public')->path('_resized/1600/products/small.jpg.webp'));
        $this->assertSame(300, $width);
    }

    public function test_deleting_a_product_photo_removes_resized_copies(): void
    {
        $product = $this->product('Coat');
        $image = $product->colours->first()->images->first();
        Img::make($image->path, 400);
        Storage::disk('public')->assertExists('_resized/400/'.$image->path.'.webp');

        $image->delete();

        Storage::disk('public')->assertMissing($image->path);
        Storage::disk('public')->assertMissing('_resized/400/'.$image->path.'.webp');
    }

    public function test_shop_pages_use_responsive_images(): void
    {
        $this->product('Coat', ['is_featured' => true]);

        $this->get('/')->assertOk()->assertSee('srcset=', false)->assertSee('/img/1600/products/coat.jpg', false);
        $this->get('/p/coat')->assertOk()->assertSee('/img/2400/products/coat.jpg 2400w', false);
    }

    /* ───── Home page editor ───── */

    public function test_without_sections_the_automatic_home_page_is_used(): void
    {
        $this->product('Coat', ['is_featured' => true]);

        $this->get('/')->assertOk()->assertSee('New season')->assertSee('Coat');
    }

    public function test_home_page_renders_saved_sections_in_order(): void
    {
        $this->product('Old Coat');
        $new = $this->product('Silk Shirt', ['compare_at_price' => 200000]);
        $collection = Collection::create(['name' => 'Gift Guide', 'slug' => 'gift-guide']);
        $new->collections()->attach($collection);

        Setting::saveMany(['home_sections' => [
            ['type' => 'hero', 'data' => ['media' => 'image', 'image' => $this->storeImage('home/hero.jpg'), 'heading' => 'Winter Collection', 'button_text' => 'Discover', 'link' => '/c/woman', 'text_colour' => 'light', 'height' => 'full']],
            ['type' => 'products', 'data' => ['title' => 'Gifts', 'source' => 'collection', 'collection_id' => $collection->id, 'limit' => 4, 'link_text' => null, 'link' => null]],
            ['type' => 'products', 'data' => ['title' => 'On sale', 'source' => 'sale', 'limit' => 4, 'link' => null]],
            ['type' => 'products', 'data' => ['title' => 'Deleted collection', 'source' => 'collection', 'collection_id' => 999, 'limit' => 4]],
            ['type' => 'text', 'data' => ['heading' => 'Our story', 'body' => 'Made in Cape Town.', 'align' => 'center']],
        ]]);

        $this->get('/')->assertOk()
            ->assertSeeInOrder(['Winter Collection', 'Gifts', 'Silk Shirt', 'Our story'])
            ->assertSee(route('shop.collection', 'gift-guide'), false)  // automatic "Shop all" link
            ->assertDontSee('On sale')                                 // prices are hidden, so sale rows are skipped
            ->assertDontSee('Old Coat')                                // not in the collection
            ->assertDontSee('Deleted collection')                      // broken section is skipped
            ->assertDontSee('New season');                              // automatic home page not used
    }

    public function test_admin_can_build_the_home_page(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'staff']));
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->get('/admin/home-page')->assertOk();

        Livewire::test(ManageHomePage::class)
            ->fillForm(['sections' => [
                Str::uuid()->toString() => ['type' => 'text', 'data' => ['heading' => 'Hello', 'body' => 'Welcome', 'align' => 'left', 'button_text' => 'Read', 'link' => '/pages/about']],
                Str::uuid()->toString() => ['type' => 'products', 'data' => ['title' => 'New in', 'source' => 'latest', 'limit' => 8, 'link' => '/collections/new-arrivals']],
            ]])
            ->call('save')
            ->assertHasNoFormErrors();

        $saved = Setting::get('home_sections');
        $this->assertSame(['text', 'products'], array_column($saved, 'type'));
        $this->assertSame('Hello', $saved[0]['data']['heading']);
    }

    public function test_admin_can_add_an_about_section_shown_on_the_home_page(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'staff']));
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $image = $this->storeImage('home/about.jpg');

        Livewire::test(ManageHomePage::class)
            ->fillForm(['sections' => [
                Str::uuid()->toString() => ['type' => 'about', 'data' => ['heading' => 'Who We Are', 'body' => 'Started in 2004.', 'image' => [Str::uuid()->toString() => $image]]],
            ]])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame($image, Setting::get('home_sections')[0]['data']['image']);

        $this->get('/')->assertOk()
            ->assertSeeInOrder(['Who We Are', 'Started in 2004.'])
            ->assertSee(Img::url($image, 1200), false);
    }

    public function test_admin_can_add_a_what_we_offer_section(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'staff']));
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(ManageHomePage::class)
            ->fillForm(['sections' => [
                Str::uuid()->toString() => ['type' => 'offer', 'data' => ['heading' => 'What we can offer', 'items' => [
                    Str::uuid()->toString() => ['text' => 'Factory & Product Sourcing'],
                    Str::uuid()->toString() => ['text' => 'Sampling Follow-up'],
                ]]],
            ]])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(['Factory & Product Sourcing', 'Sampling Follow-up'], array_values(Setting::get('home_sections')[0]['data']['items']));

        // Reopening the admin page and saving again keeps the options.
        Livewire::test(ManageHomePage::class)->call('save')->assertHasNoFormErrors();
        $this->assertSame(['Factory & Product Sourcing', 'Sampling Follow-up'], array_values(Setting::get('home_sections')[0]['data']['items']));

        $this->get('/')->assertOk()
            ->assertSeeInOrder(['What we can offer', 'Factory &amp; Product Sourcing', 'Sampling Follow-up'], false);
    }

    public function test_home_page_links_must_be_local_paths_or_web_addresses(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(ManageHomePage::class)
            ->fillForm(['sections' => [
                Str::uuid()->toString() => ['type' => 'text', 'data' => ['heading' => 'X', 'align' => 'left', 'link' => 'javascript:alert(1)']],
            ]])
            ->call('save')
            ->assertHasFormErrors();

        $this->assertNull(Setting::get('home_sections'));
    }
}

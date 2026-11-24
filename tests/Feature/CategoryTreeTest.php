<?php

namespace Tests\Feature;

use App\Filament\Resources\Categories\Pages\EditCategory;
use App\Filament\Resources\Categories\Pages\ListCategories;
use App\Models\Category;
use App\Models\User;
use Database\Seeders\CatalogueSeeder;
use Filament\Actions\DeleteAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CategoryTreeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_seeder_adds_kids_with_girls_and_boys(): void
    {
        $this->seed(CatalogueSeeder::class);
        $this->seed(CatalogueSeeder::class); // safe to re-run

        $kids = Category::where('slug', 'kids')->firstOrFail();
        $this->assertNull($kids->parent_id);
        $this->assertSame(['Girls', 'Boys'], $kids->children->pluck('name')->all());
        $this->assertTrue(Category::where('slug', 'kids-girls-jeans')->exists());
        $this->assertTrue(Category::where('slug', 'kids-boys-jeans')->exists());

        $this->get('/')->assertSee('Kids');
        $this->get('/c/kids-girls-jeans')->assertOk()->assertSee('Girls’ Jeans');
    }

    public function test_add_subcategory_prefills_the_parent(): void
    {
        $kids = Category::create(['name' => 'Kids', 'slug' => 'kids']);

        $this->get('/admin/categories/create?parent='.$kids->id)->assertOk();
        Livewire::withQueryParams(['parent' => $kids->id])
            ->test(\App\Filament\Resources\Categories\Pages\CreateCategory::class)
            ->assertSchemaStateSet(['parent_id' => $kids->id]);
    }

    public function test_a_category_with_subcategories_cannot_be_deleted(): void
    {
        $kids = Category::create(['name' => 'Kids', 'slug' => 'kids']);
        $girls = Category::create(['name' => 'Girls', 'slug' => 'kids-girls', 'parent_id' => $kids->id]);

        Livewire::test(EditCategory::class, ['record' => $kids->getRouteKey()])
            ->callAction(DeleteAction::class)
            ->assertNotified();
        $this->assertModelExists($kids);

        // Leaf categories (e.g. a "Sweater" the admin added) delete normally.
        Livewire::test(ListCategories::class)->callTableAction(DeleteAction::class, $girls);
        $this->assertModelMissing($girls);
    }
}

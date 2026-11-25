<?php

namespace Tests\Feature;

use App\Filament\Pages\ManageSettings;
use App\Filament\Resources\Pages\Pages\EditPage;
use App\Models\ContactMessage;
use App\Models\Faq;
use App\Models\Page;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\ContentSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class ContentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(ContentSeeder::class);
    }

    public function test_seeded_pages_show_and_appear_in_footer(): void
    {
        $this->get('/pages/shipping-returns')->assertOk()->assertSee('Shipping &amp; Returns', false)->assertSee('Refunds');
        $this->get('/pages/size-guide')->assertOk()->assertSee('<table>', false);

        $this->get('/')->assertSee(route('shop.page', 'privacy'))->assertSee(route('shop.contact'))->assertSee(route('shop.faq'));
    }

    public function test_unpublished_page_is_hidden(): void
    {
        Page::where('slug', 'careers')->update(['is_active' => false]);

        $this->get('/pages/careers')->assertNotFound();
        $this->get('/')->assertDontSee(route('shop.page', 'careers'));
    }

    public function test_page_html_is_sanitised(): void
    {
        Page::create(['title' => 'Test', 'slug' => 'test', 'body' => '<p>Hello</p><script>alert(1)</script>']);

        $this->get('/pages/test')->assertOk()->assertSee('Hello')->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_faq_page_groups_by_topic(): void
    {
        Faq::where('question', 'like', 'Can I change%')->update(['is_active' => false]);

        $this->get('/faq')->assertOk()
            ->assertSee('Delivery')
            ->assertSee('Do you ship internationally?')
            ->assertDontSee('Can I change or cancel my order?');
    }

    public function test_contact_form_saves_message_and_emails_shop(): void
    {
        Mail::fake();

        $this->post('/contact', [
            'name' => 'Thandi', 'phone' => '+27 82 123 4567', 'email' => 'thandi@example.com', 'subject' => 'Sizing advice',
            'message' => 'Which size should I take in the jacket?',
        ])->assertRedirect('/contact')->assertSessionHas('sent');

        $this->assertDatabaseHas('contact_messages', ['phone' => '+27 82 123 4567', 'email' => 'thandi@example.com', 'subject' => 'Sizing advice']);
        $this->get('/contact')->assertSee('Thank you')->assertSee('+27 82 123 4567');
    }

    public function test_contact_form_email_is_optional(): void
    {
        Mail::fake();

        $this->post('/contact', [
            'name' => 'Sipho', 'phone' => '+27821234567', 'subject' => 'Delivery',
            'message' => 'When will my parcel arrive?',
        ])->assertSessionHasNoErrors()->assertSessionHas('sent');

        $this->assertDatabaseHas('contact_messages', ['name' => 'Sipho', 'email' => null]);
    }

    public function test_contact_form_needs_phone_with_country_code(): void
    {
        $base = ['name' => 'Sipho', 'subject' => 'Delivery', 'message' => 'When will my parcel arrive?'];

        $this->post('/contact', $base)->assertSessionHasErrors('phone');
        $this->post('/contact', $base + ['phone' => '082 123 4567'])->assertSessionHasErrors('phone');
        $this->post('/contact', $base + ['phone' => '+27 12'])->assertSessionHasErrors('phone');
        $this->assertSame(0, ContactMessage::count());
    }

    public function test_contact_page_hides_whatsapp_when_not_set(): void
    {
        $this->get('/contact')->assertOk()->assertDontSee('wa.me', false);
    }

    public function test_contact_page_shows_whatsapp_when_set(): void
    {
        Setting::saveMany(['contact_whatsapp' => '+27 82 555 0101']);

        $this->get('/contact')->assertOk()
            ->assertSee('https://wa.me/27825550101?text=', false)
            ->assertSee('Chat on WhatsApp')
            ->assertDontSee('+27 82 555 0101'); // number stays hidden; only the button shows
        $this->get('/')->assertDontSee('wa.me/27825550101', false); // contact page only
    }

    public function test_contact_form_validates_and_blocks_bots(): void
    {
        $this->post('/contact', ['name' => '', 'email' => 'nope', 'subject' => 'Hack', 'message' => 'hi'])
            ->assertSessionHasErrors(['name', 'phone', 'email', 'subject', 'message']);

        $this->post('/contact', [
            'name' => 'Bot', 'phone' => '+27 82 123 4567', 'subject' => 'Other',
            'message' => 'Buy cheap things now!!!', 'website' => 'http://spam.example',
        ])->assertSessionHas('sent');

        $this->assertSame(0, ContactMessage::count());
    }

    public function test_admin_can_save_store_settings_and_shop_uses_them(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(ManageSettings::class)
            ->fillForm([
                'announcement_enabled' => true,
                'announcement_text' => 'Winter sale now on',
                'contact_email' => 'shop@example.com',
                'contact_phone' => '+27 21 000 0000',
                'social_instagram' => 'https://instagram.com/example',
                'product_notes' => [['text' => 'Free delivery over R 2 000']],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('shop@example.com', Setting::get('contact_email'));
        $this->assertSame(['Free delivery over R 2 000'], Setting::get('product_notes'));

        // Re-opening the page shows the saved note.
        $notes = Livewire::test(ManageSettings::class)->get('data.product_notes');
        $this->assertSame(['Free delivery over R 2 000'], array_column(array_values($notes), 'text'));

        $this->get('/')->assertSee('Winter sale now on')->assertSee('https://instagram.com/example');
        $this->get('/contact')->assertSee('+27 21 000 0000');
    }

    public function test_staff_cannot_open_store_settings(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'staff']));

        $this->get('/admin/manage-settings')->assertForbidden();
        $this->get('/admin/pages')->assertOk();
        $this->get('/admin/faqs')->assertOk();
        $this->get('/admin/contact-messages')->assertOk();
    }

    /* ───── About page: Meet Our Leadership ───── */

    public function test_admin_can_add_leaders_to_the_about_page_only(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $about = Page::where('slug', 'about')->first();
        $careers = Page::where('slug', 'careers')->first();

        Livewire::test(EditPage::class, ['record' => $about->getRouteKey()])
            ->assertFormFieldIsVisible('team_section.heading')
            ->assertFormFieldIsDisabled('slug')
            ->fillForm(['team_section' => [
                'enabled' => true,
                'heading' => 'Our People',
                'subtitle' => 'Behind the brand.',
                'shape' => 'circle',
                'members' => [
                    ['name' => 'Umar Faruk', 'designation' => 'Managing Director', 'tag' => 'Leadership', 'bio' => 'Leads the business.', 'link' => 'https://www.linkedin.com/in/umar'],
                ],
            ]])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Our People', $about->fresh()->team_section['heading']);
        $this->assertSame('about', $about->fresh()->slug);

        Livewire::test(EditPage::class, ['record' => $careers->getRouteKey()])
            ->assertFormFieldIsHidden('team_section.heading');
    }

    public function test_about_page_shows_side_image_next_to_the_content(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        Livewire::test(EditPage::class, ['record' => Page::where('slug', 'careers')->first()->getRouteKey()])
            ->assertFormFieldIsHidden('side_image');
        Livewire::test(EditPage::class, ['record' => Page::where('slug', 'about')->first()->getRouteKey()])
            ->assertFormFieldIsVisible('side_image');

        $this->get('/pages/about')->assertOk()->assertDontSee('rich-split');

        Page::where('slug', 'about')->first()->update(['side_image' => 'pages/about-side.jpg']);
        $this->get('/pages/about')->assertOk()
            ->assertSeeInOrder(['rich-split', 'Tell your brand story', 'about-side'], false);
    }

    public function test_about_page_shows_leaders_after_the_content(): void
    {
        Page::where('slug', 'about')->first()->update(['team_section' => [
            'enabled' => true, 'heading' => 'Meet Our Leadership', 'subtitle' => 'Behind the brand.', 'shape' => 'portrait',
            'members' => [['name' => 'Umar Faruk', 'designation' => 'Managing Director', 'tag' => 'Leadership', 'link' => 'javascript:alert(1)']],
        ]]);

        $this->get('/pages/about')->assertOk()
            ->assertSeeInOrder(['Tell your brand story', 'Meet Our Leadership', '01 / Leadership', 'Umar Faruk', 'Managing Director'])
            ->assertDontSee('javascript:alert', false);

        Page::where('slug', 'about')->first()->update(['team_section' => ['enabled' => false, 'heading' => 'Meet Our Leadership', 'members' => [['name' => 'Umar Faruk']]]]);
        $this->get('/pages/about')->assertOk()->assertDontSee('Meet Our Leadership');
    }
}

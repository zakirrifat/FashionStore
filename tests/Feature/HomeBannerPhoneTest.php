<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Support\Img;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HomeBannerPhoneTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('public');
    }

    private function image(string $name, int $width, int $height): string
    {
        return UploadedFile::fake()->image($name, $width, $height)->storeAs('home', $name, 'public');
    }

    private function slideshow(array $slides): void
    {
        Setting::saveMany(['home_sections' => [['type' => 'slideshow', 'data' => [
            'seconds' => 6, 'transition' => 'fade', 'height' => 'large', 'slides' => $slides,
        ]]]]);
    }

    public function test_image_ratio_is_read_from_the_file(): void
    {
        $this->assertEqualsWithDelta(2.0, Img::ratio($this->image('wide.jpg', 1000, 500)), 0.001);
        $this->assertNull(Img::ratio('home/missing.jpg'));
    }

    public function test_phone_frame_fits_the_tallest_banner_and_shows_it_whole(): void
    {
        $this->slideshow([
            ['media' => 'image', 'image' => $this->image('wide.jpg', 1920, 819)],
            ['media' => 'image', 'image' => $this->image('normal.jpg', 1600, 900)],
        ]);

        $this->get('/')->assertOk()
            ->assertSee('--phone-ratio: 1.7778; --phone-extra: 6rem; --phone-text: 0rem', false)
            ->assertSee('max-md:object-contain', false)
            ->assertSee('md:h-[78svh]', false);
    }

    public function test_banner_text_gets_its_own_row_under_the_picture_on_phones(): void
    {
        $this->slideshow([[
            'media' => 'image',
            'image' => $this->image('normal.jpg', 1600, 900),
            'button_text' => 'Explore more',
            'link' => '/c/woman',
        ]]);

        $this->get('/')->assertOk()->assertSee('--phone-extra: 7.5rem; --phone-text: 3.5rem', false);
    }

    public function test_phone_image_sets_the_frame_when_uploaded(): void
    {
        $this->slideshow([[
            'media' => 'image',
            'image' => $this->image('wide.jpg', 1920, 819),
            'mobile_image' => $this->image('phone.jpg', 1080, 1350),
        ]]);

        $this->get('/')->assertOk()->assertSee('--phone-ratio: 0.8000; --phone-extra: 4rem', false);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Hill;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FetchHillPhotosTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_extracts_and_stores_the_photo_url(): void
    {
        $hill = Hill::factory()->create([
            'walkhighlands_url' => 'https://www.walkhighlands.co.uk/munros/ben-nevis',
            'photo_url' => null,
        ]);

        Http::fake([
            'https://www.walkhighlands.co.uk/munros/ben-nevis' => Http::response(
                '<html><body><img src="/images/walkhighlands.png" alt="logo"><img src="https://www.walkhighlands.co.uk/munros/ben-nevis-1.JPG" alt="Ben Nevis"></body></html>'
            ),
        ]);

        $this->artisan('hills:fetch-photos')->assertSuccessful();

        $this->assertSame(
            'https://www.walkhighlands.co.uk/munros/ben-nevis-1.JPG',
            $hill->fresh()->photo_url
        );
    }

    public function test_it_skips_hills_that_already_have_a_photo_unless_forced(): void
    {
        $hill = Hill::factory()->create([
            'walkhighlands_url' => 'https://www.walkhighlands.co.uk/munros/ben-nevis',
            'photo_url' => 'https://www.walkhighlands.co.uk/munros/existing.jpg',
        ]);

        Http::fake();

        $this->artisan('hills:fetch-photos')->assertSuccessful();

        Http::assertNothingSent();
        $this->assertSame('https://www.walkhighlands.co.uk/munros/existing.jpg', $hill->fresh()->photo_url);
    }

    public function test_it_leaves_photo_url_null_when_no_image_is_found(): void
    {
        $hill = Hill::factory()->create([
            'walkhighlands_url' => 'https://www.walkhighlands.co.uk/munros/no-photo-hill',
            'photo_url' => null,
        ]);

        Http::fake([
            'https://www.walkhighlands.co.uk/munros/no-photo-hill' => Http::response(
                '<html><body><img src="/images/walkhighlands.png" alt="logo"></body></html>'
            ),
        ]);

        $this->artisan('hills:fetch-photos')->assertSuccessful();

        $this->assertNull($hill->fresh()->photo_url);
    }
}

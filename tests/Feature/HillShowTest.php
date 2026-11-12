<?php

namespace Tests\Feature;

use App\Models\Hill;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HillShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_can_view_a_hill_detail_page(): void
    {
        $hill = Hill::factory()->create([
            'name' => 'Ben Nevis',
            'slug' => 'ben-nevis',
            'height_m' => 1345,
            'region' => 'West Highlands',
            'grid_ref' => 'NN166712',
            'rhb_section' => '04A',
            'rhb_section_description' => 'Fort William to Loch Treig and Loch Leven',
        ]);

        $response = $this->get(route('hills.show', $hill));

        $response->assertOk();
        $response->assertSee('Ben Nevis');
        $response->assertSee('1345');
        $response->assertSee('NN166712');
        $response->assertSee('04A');
        $response->assertSee('Fort William to Loch Treig and Loch Leven');
    }

    public function test_it_links_out_to_walkhighlands_and_mwis(): void
    {
        $hill = Hill::factory()->create([
            'walkhighlands_url' => 'https://www.walkhighlands.co.uk/munros/ben-nevis',
            'mwis_forecast_url' => 'https://www.mwis.org.uk/forecasts/scottish/west-highlands',
        ]);

        $response = $this->get(route('hills.show', $hill));

        $response->assertSee('https://www.walkhighlands.co.uk/munros/ben-nevis', false);
        $response->assertSee('https://www.mwis.org.uk/forecasts/scottish/west-highlands', false);
    }

    public function test_photo_is_only_shown_when_present(): void
    {
        $withPhoto = Hill::factory()->withPhoto()->create();
        $withoutPhoto = Hill::factory()->create(['photo_url' => null]);

        $this->get(route('hills.show', $withPhoto))
            ->assertSee($withPhoto->photo_url, false);

        $this->get(route('hills.show', $withoutPhoto))
            ->assertOk()
            ->assertDontSee('<figure', false)
            ->assertDontSee('CC BY-SA 2.0');
    }

    public function test_photo_links_to_geograph_and_credits_the_photographer(): void
    {
        $hill = Hill::factory()->create([
            'photo_url' => 'https://s1.geograph.org.uk/geophotos/08/35/75/8357549_3504571a.jpg',
            'photo_page_url' => 'https://www.geograph.org.uk/photo/8357549',
            'photo_title' => 'Ben Nevis summit',
            'photo_author' => 'Anthony Foster',
            'photo_geograph_id' => 8357549,
        ]);

        $this->get(route('hills.show', $hill))
            ->assertSee('src="https://s1.geograph.org.uk/geophotos/08/35/75/8357549_3504571a.jpg"', false)
            ->assertSee('href="https://www.geograph.org.uk/photo/8357549"', false)
            ->assertSee('alt="Ben Nevis summit"', false)
            ->assertSeeInOrder(['&copy; Anthony Foster,', 'https://creativecommons.org/licenses/by-sa/2.0/', 'CC BY-SA 2.0'], false);
    }

    public function test_photographer_name_is_escaped(): void
    {
        $hill = Hill::factory()->withPhoto()->create(['photo_author' => '<script>alert(1)</script>']);

        $this->get(route('hills.show', $hill))
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
    }

    public function test_unknown_hill_slug_returns_404(): void
    {
        $this->get('/hills/not-a-real-hill')->assertNotFound();
    }
}

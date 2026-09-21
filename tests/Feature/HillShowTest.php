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
        $withPhoto = Hill::factory()->create(['photo_url' => 'https://www.walkhighlands.co.uk/munros/ben-nevis-1.JPG']);
        $withoutPhoto = Hill::factory()->create(['photo_url' => null]);

        $this->get(route('hills.show', $withPhoto))
            ->assertSee('https://www.walkhighlands.co.uk/munros/ben-nevis-1.JPG', false);

        $this->get(route('hills.show', $withoutPhoto))
            ->assertOk();
    }

    public function test_unknown_hill_slug_returns_404(): void
    {
        $this->get('/hills/not-a-real-hill')->assertNotFound();
    }
}

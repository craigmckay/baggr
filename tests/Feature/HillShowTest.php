<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Hill;
use App\Models\HillClass;
use App\Models\OsMap;
use App\Models\User;
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
            'grid_ref' => 'NN166712',
            'dobih_section' => '4.1',
        ]);
        Area::create(['code' => 'M04', 'short_name' => 'M04', 'name' => '04: Western Highlands', 'type' => 'murray_section', 'country' => 'S']);
        $hill->areas()->attach(Area::create(['code' => 'M04A', 'short_name' => 'M04A', 'name' => '04A: Fort William to Loch Treig and Loch Leven', 'type' => 'murray_section', 'country' => 'S']));

        $response = $this->get(route('hills.show', $hill));

        $response->assertOk();
        $response->assertSee('Ben Nevis');
        $response->assertSee('1345');
        $response->assertSee('NN166712');
        $response->assertSee('Murray section');
        $response->assertSee('4. Western Highlands');
        $response->assertSee('Murray sub-section');
        $response->assertSee('04A: Fort William to Loch Treig and Loch Leven');
    }

    public function test_it_links_out_to_walkhighlands_and_mwis(): void
    {
        $hill = Hill::factory()->create([
            'walkhighlands_url' => 'https://www.walkhighlands.co.uk/munros/ben-nevis',
            'mwis_forecast_url' => 'https://www.mwis.org.uk/forecasts/scottish/west-highlands',
        ]);

        $response = $this->actingAs(User::factory()->create())->get(route('hills.show', $hill));

        $response->assertSee('https://www.walkhighlands.co.uk/munros/ben-nevis', false);
        $response->assertSee('https://www.mwis.org.uk/forecasts/scottish/west-highlands', false);
    }

    public function test_a_hill_without_links_or_photo_still_shows(): void
    {
        $hill = Hill::factory()->create([
            'name' => 'Ben Cleuch',
            'slug' => 'ben-cleuch',
            'country' => 'S',
            'walkhighlands_url' => null,
            'mwis_forecast_url' => null,
            'dobih_section' => null,
            'drop_m' => 595,
        ]);

        $this->actingAs(User::factory()->create())->get(route('hills.show', $hill))
            ->assertOk()
            ->assertSee('Ben Cleuch')
            ->assertSee('Scotland')
            ->assertSee('595.0m')
            ->assertDontSee('Walkhighlands')
            ->assertDontSee('MWIS')
            ->assertDontSee('Murray section');
    }

    public function test_it_lists_the_hills_lists_areas_and_maps(): void
    {
        $hill = Hill::factory()->create();
        $hill->classes()->attach(HillClass::create(['code' => 'G', 'name' => 'Graham', 'show_in_list' => true, 'sort_order' => 1]));
        $hill->classes()->attach(HillClass::create(['code' => 'HHB', 'name' => 'Hidden', 'show_in_list' => false, 'sort_order' => 2]));
        $hill->areas()->attach(Area::create(['code' => 'D002', 'short_name' => 'Ochils', 'name' => 'Ochil Hills', 'type' => 'hill_range', 'country' => 'S']));
        $hill->maps()->attach(OsMap::create(['type' => 'OS50', 'ref' => '058', 'name' => 'Perth']));

        $this->get(route('hills.show', $hill))
            ->assertSee('Graham')
            ->assertDontSee('Hidden')
            ->assertSee('Ochil Hills')
            ->assertSee('OS Landranger')
            ->assertSee('058');
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

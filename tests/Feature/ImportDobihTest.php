<?php

namespace Tests\Feature;

use App\Models\Hill;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ImportDobihTest extends TestCase
{
    use RefreshDatabase;

    private function fixtures(): string
    {
        return base_path('tests/Fixtures/dobih');
    }

    public function test_it_adds_every_hill_with_its_dobih_fields(): void
    {
        $this->artisan('dobih:import', ['path' => $this->fixtures()])->assertSuccessful();

        $this->assertSame(4, Hill::count());

        $hill = Hill::where('slug', 'ben-cleuch')->firstOrFail();
        $this->assertSame('Ben Cleuch', $hill->name);
        $this->assertSame(721, $hill->height_m);
        $this->assertSame('NN902006', $hill->grid_ref);
        $this->assertSame('S', $hill->country);
        $this->assertEquals(595.0, $hill->drop_m);
        $this->assertSame('2020-01-01', $hill->revised_on->toDateString());
        $this->assertNull($hill->walkhighlands_url);
    }

    public function test_hills_sharing_a_name_get_distinct_slugs(): void
    {
        $this->artisan('dobih:import', ['path' => $this->fixtures()])->assertSuccessful();

        $this->assertEqualsCanonicalizing(
            ['heddon-3', 'heddon-4'],
            Hill::where('name', 'Heddon')->pluck('slug')->all(),
        );
    }

    public function test_existing_munros_keep_their_own_details(): void
    {
        $nevis = Hill::factory()->withPhoto()->create([
            'name' => 'Ben Nevis',
            'slug' => 'ben-nevis',
            'dobih_number' => 1,
            'walkhighlands_url' => 'https://www.walkhighlands.co.uk/munros/ben-nevis',
            'height_m' => 1345,
        ]);

        $this->artisan('dobih:import', ['path' => $this->fixtures()])->assertSuccessful();

        $nevis->refresh();

        $this->assertSame(4, Hill::count());
        $this->assertSame('ben-nevis', $nevis->slug);
        $this->assertSame('https://www.walkhighlands.co.uk/munros/ben-nevis', $nevis->walkhighlands_url);
        $this->assertNotNull($nevis->photo_url);
        $this->assertSame(1345, $nevis->height_m);
        $this->assertEquals(1344.5, $nevis->metres);
    }

    public function test_it_links_names_areas_lists_and_maps(): void
    {
        $this->artisan('dobih:import', ['path' => $this->fixtures()])->assertSuccessful();

        $nevis = Hill::where('slug', 'ben-nevis')->firstOrFail();
        $cleuch = Hill::where('slug', 'ben-cleuch')->firstOrFail();

        $this->assertSame(['Beinn Nibheis', 'Ben Nevis'], $nevis->alternateNames->pluck('name')->sort()->values()->all());
        $this->assertEqualsCanonicalizing(['Ma', 'M'], $nevis->classes->pluck('code')->all());
        $this->assertEqualsCanonicalizing(['Ma', 'G', 'D'], $cleuch->classes->pluck('code')->all());

        $this->assertEqualsCanonicalizing(['D002', 'C001'], $cleuch->areas->pluck('code')->all());
        $this->assertSame('hill_range', $cleuch->areas->firstWhere('code', 'D002')->type);
        $this->assertSame('county', $cleuch->areas->firstWhere('code', 'C001')->type);
        $this->assertTrue((bool) $cleuch->areas->firstWhere('code', 'C001')->pivot->is_county_top);

        $this->assertEqualsCanonicalizing(['392W', '041'], $nevis->maps->pluck('ref')->all());
    }

    public function test_the_all_row_is_not_an_area_and_unknown_links_are_skipped(): void
    {
        $this->artisan('dobih:import', ['path' => $this->fixtures()])
            ->expectsOutputToContain('Skipped 2 links')
            ->assertSuccessful();

        $this->assertSame(3, DB::table('areas')->count());
        $this->assertSame(0, DB::table('areas')->where('code', '(All)')->count());
    }

    public function test_running_it_twice_changes_nothing(): void
    {
        $this->artisan('dobih:import', ['path' => $this->fixtures()])->assertSuccessful();
        $first = [Hill::count(), DB::table('area_hill')->count(), DB::table('class_hill')->count(), DB::table('hill_names')->count(), DB::table('hill_map')->count()];

        $this->artisan('dobih:import', ['path' => $this->fixtures()])->assertSuccessful();
        $second = [Hill::count(), DB::table('area_hill')->count(), DB::table('class_hill')->count(), DB::table('hill_names')->count(), DB::table('hill_map')->count()];

        $this->assertSame($first, $second);
    }

    public function test_it_fails_when_a_file_is_missing(): void
    {
        $this->artisan('dobih:import', ['path' => base_path('tests/Fixtures')])
            ->expectsOutputToContain('Missing')
            ->assertFailed();
    }
}

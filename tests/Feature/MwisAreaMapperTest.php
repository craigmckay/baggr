<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Hill;
use App\Support\MwisAreaMapper;
use App\Support\MwisAreas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MwisAreaMapperTest extends TestCase
{
    use RefreshDatabase;

    /** @param  array<string, mixed>  $attributes */
    private function hillIn(array $areas, array $attributes = []): Hill
    {
        $hill = Hill::factory()->create($attributes + ['mwis_forecast_url' => null]);

        foreach ($areas as $code => $type) {
            $area = Area::firstOrCreate(['code' => $code], ['name' => $code, 'short_name' => $code, 'type' => $type, 'country' => 'S']);
            $hill->areas()->attach($area);
        }

        return $hill;
    }

    private function mapped(Hill $hill): ?string
    {
        return $hill->fresh()->mwis_area;
    }

    public function test_a_munro_keeps_the_area_its_forecast_link_points_at(): void
    {
        $munro = $this->hillIn(['M30B' => 'murray_section'], [
            'mwis_forecast_url' => 'https://www.mwis.org.uk/forecasts/scottish/the-northwest-highlands',
        ]);

        app(MwisAreaMapper::class)->run();

        // Its Murray section would say Snowdonia; the curated link wins.
        $this->assertSame('NW', $this->mapped($munro));
    }

    public function test_a_hill_takes_the_area_of_its_murray_section(): void
    {
        $ochil = $this->hillIn(['M26A' => 'murray_section']);
        $arran = $this->hillIn(['M20C' => 'murray_section']);

        app(MwisAreaMapper::class)->run();

        $this->assertSame('SH', $this->mapped($ochil));
        $this->assertSame('WH', $this->mapped($arran));
    }

    public function test_a_named_area_beats_the_murray_section(): void
    {
        $cheviot = $this->hillIn(['NE08' => 'named_area', 'M34D' => 'murray_section']);
        $fell = $this->hillIn(['NE05' => 'named_area', 'M36' => 'murray_section']);

        app(MwisAreaMapper::class)->run();

        $this->assertSame('SU', $this->mapped($cheviot));
        $this->assertSame('LD', $this->mapped($fell));
    }

    public function test_hills_mwis_does_not_name_are_labelled_without_changing_their_area(): void
    {
        $lomond = $this->hillIn(['M26A' => 'murray_section']);
        $ochil = $this->hillIn(['M26A' => 'murray_section', 'D002' => 'hill_range']);
        $islay = $this->hillIn(['M20B' => 'murray_section']);
        $arran = $this->hillIn(['M20C' => 'murray_section']);
        $cowal = $this->hillIn(['M19C' => 'murray_section']);
        $fell = $this->hillIn(['NE05' => 'named_area', 'M34A' => 'murray_section']);
        $fringe = $this->hillIn(['M34A' => 'murray_section']);

        app(MwisAreaMapper::class)->run();

        $nearest = fn (Hill $hill) => $hill->fresh()->mwis_nearest;

        $this->assertSame('SH', $this->mapped($lomond));
        $this->assertTrue($nearest($lomond));
        $this->assertTrue($nearest($islay));
        $this->assertTrue($nearest($fringe));
        $this->assertFalse($nearest($ochil));
        $this->assertFalse($nearest($arran));
        $this->assertFalse($nearest($cowal));
        $this->assertFalse($nearest($fell));
    }

    public function test_a_hill_with_its_own_forecast_link_is_never_labelled_nearest(): void
    {
        $munro = $this->hillIn(['M26A' => 'murray_section'], [
            'mwis_forecast_url' => 'https://www.mwis.org.uk/forecasts/scottish/southeastern-highlands',
        ]);

        app(MwisAreaMapper::class)->run();

        $this->assertFalse($munro->fresh()->mwis_nearest);
    }

    public function test_hills_outside_the_ten_areas_have_none(): void
    {
        $irish = $this->hillIn(['M50C' => 'murray_section']);
        $shetland = $this->hillIn(['M22' => 'murray_section']);
        $unlisted = $this->hillIn([]);

        $result = app(MwisAreaMapper::class)->run();

        $this->assertNull($this->mapped($irish));
        $this->assertNull($this->mapped($shetland));
        $this->assertNull($this->mapped($unlisted));
        $this->assertSame(0, $result['mapped']);
        $this->assertSame(3, $result['none']);
    }

    public function test_a_hill_in_a_straddling_section_takes_the_nearest_munros_area(): void
    {
        $this->hillIn(['M04B' => 'murray_section'], [
            'mwis_forecast_url' => 'https://www.mwis.org.uk/forecasts/scottish/west-highlands',
            'latitude' => 56.8, 'longitude' => -4.8,
        ]);
        $this->hillIn(['M04B' => 'murray_section'], [
            'mwis_forecast_url' => 'https://www.mwis.org.uk/forecasts/scottish/cairngorms-np-and-monadhliath',
            'latitude' => 56.9, 'longitude' => -4.0,
        ]);
        $west = $this->hillIn(['M04B' => 'murray_section'], ['latitude' => 56.81, 'longitude' => -4.75]);
        $east = $this->hillIn(['M04B' => 'murray_section'], ['latitude' => 56.9, 'longitude' => -4.05]);

        $result = app(MwisAreaMapper::class)->run();

        $this->assertSame('WH', $this->mapped($west));
        $this->assertSame('EH', $this->mapped($east));
        $this->assertSame(2, $result['nearest']);
    }

    public function test_the_mapping_table_is_written_to_the_areas(): void
    {
        $this->hillIn(['M26A' => 'murray_section', 'NE08' => 'named_area', 'M50C' => 'murray_section']);

        app(MwisAreaMapper::class)->run();

        $this->assertSame('SH', Area::where('code', 'M26A')->value('mwis_area'));
        $this->assertSame('SU', Area::where('code', 'NE08')->value('mwis_area'));
        $this->assertNull(Area::where('code', 'M50C')->value('mwis_area'));
    }

    public function test_running_it_again_starts_afresh(): void
    {
        $hill = $this->hillIn(['M26A' => 'murray_section']);
        app(MwisAreaMapper::class)->run();

        Area::where('code', 'M26A')->first()->hills()->detach($hill->id);
        app(MwisAreaMapper::class)->run();

        $this->assertNull($this->mapped($hill));
    }

    public function test_the_command_reports_what_it_did(): void
    {
        $this->hillIn(['M26A' => 'murray_section']);
        $this->hillIn([]);

        $this->artisan('hills:map-mwis')
            ->expectsOutputToContain('1 hills have an MWIS area')
            ->assertSuccessful();
    }

    public function test_every_mapped_code_is_a_real_mwis_area(): void
    {
        $codes = array_keys(MwisAreas::all());

        $this->assertCount(10, $codes);

        foreach ([...config('mwis.named_areas'), ...config('mwis.murray_sections')] as $mwis) {
            $this->assertContains($mwis, $codes);
        }
    }

    public function test_a_forecast_link_is_built_from_the_area(): void
    {
        $this->assertSame('https://www.mwis.org.uk/forecasts/scottish/southern-uplands', MwisAreas::find('SU')['url']);
        $this->assertSame('https://www.mwis.org.uk/forecasts/english-and-welsh/peak-district', MwisAreas::find('PD')['url']);
        $this->assertSame('WH', MwisAreas::fromUrl('https://www.mwis.org.uk/forecasts/scottish/west-highlands/')['code']);
        $this->assertNull(MwisAreas::find('XX'));
    }
}

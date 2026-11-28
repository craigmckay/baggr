<?php

namespace Tests\Feature\Weather;

use App\Actions\Walks\LogWalk;
use App\Models\Group;
use App\Models\Hill;
use App\Models\HillClass;
use App\Models\MwisForecast;
use App\Models\User;
use App\Support\Mwis\Forecasts;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class WeatherPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['mwis.enabled' => true]);
        // A Thursday, so Saturday is covered by a three-day forecast.
        $this->travelTo(CarbonImmutable::parse('2026-11-26 09:00', 'Europe/London'));
    }

    private function forecast(string $area, string $date, int $wind = 10, ?int $cloudFree = 80, ?string $headline = null): void
    {
        MwisForecast::create([
            'area' => $area,
            'date' => $date,
            'headline' => $headline,
            'wind' => "Made-up wind {$wind}mph",
            'wind_mph' => $wind,
            'rain' => "Made-up rain for {$area}",
            'cloud_free' => $cloudFree === null ? 'Hard to say' : "{$cloudFree}%",
            'cloud_free_percent' => $cloudFree,
        ]);
    }

    /** @param  array<string, mixed>  $attributes */
    private function munro(string $name, string $area, array $attributes = []): Hill
    {
        $hill = Hill::factory()->create(['name' => $name, 'mwis_area' => $area, 'mwis_forecast_url' => null] + $attributes);
        $hill->classes()->attach(HillClass::firstOrCreate(['code' => 'M'], ['name' => 'Munro', 'show_in_list' => true, 'sort_order' => 1]));

        return $hill;
    }

    private function climbed(User $user, Hill $hill): void
    {
        app(LogWalk::class)->handle($user, [
            'group_id' => null,
            'people' => [$user->id],
            'days' => [['date' => '2026-10-01', 'direction' => null, 'duration_minutes' => null, 'hills' => [$hill->id]]],
        ]);
    }

    /** @return array<string, array{string, string}> */
    public static function targetDays(): array
    {
        // Today, and the day shown when the forecast covers today and the next two days.
        return [
            'Monday' => ['2026-11-23', '2026-11-25'],
            'Tuesday' => ['2026-11-24', '2026-11-26'],
            'Wednesday' => ['2026-11-25', '2026-11-27'],
            'Thursday' => ['2026-11-26', '2026-11-28'],
            'Friday' => ['2026-11-27', '2026-11-28'],
            'Saturday' => ['2026-11-28', '2026-11-28'],
            'Sunday' => ['2026-11-29', '2026-12-01'],
        ];
    }

    #[DataProvider('targetDays')]
    public function test_the_target_day_is_saturday_if_covered_otherwise_the_furthest_day(string $today, string $expected): void
    {
        $this->travelTo(CarbonImmutable::parse("{$today} 09:00", 'Europe/London'));
        $this->actingAs(User::factory()->create());

        foreach ([0, 1, 2] as $ahead) {
            $this->forecast('WH', CarbonImmutable::parse($today)->addDays($ahead)->toDateString());
        }

        $this->assertSame($expected, (new Forecasts)->forArea('WH')?->date->toDateString());
    }

    public function test_an_old_forecast_is_still_shown_and_marked(): void
    {
        $this->actingAs(User::factory()->create());
        $this->forecast('WH', '2026-11-20');

        $this->get(route('weather'))
            ->assertSee('Made-up rain for WH')
            ->assertSee('This forecast is out of date');
    }

    public function test_the_hill_list_and_hill_page_show_the_forecast_to_signed_in_people_only(): void
    {
        $hill = $this->munro('Ben Test', 'WH');
        $this->forecast('WH', '2026-11-28', headline: 'Made-up headline');

        $this->get(route('hills.index'))->assertDontSee('MWIS forecast for')->assertDontSee('data-test="weather-rating"', false);
        $this->get(route('hills.show', $hill))->assertDontSee('Made-up headline')->assertDontSee('Made-up rain');

        $me = User::factory()->create();

        $this->actingAs($me)->get(route('hills.index'))
            ->assertSee('MWIS forecast for Saturday 28 November')
            ->assertSee('data-test="weather-rating"', false);

        $this->actingAs($me)->get(route('hills.show', $hill))
            ->assertSeeInOrder(['Saturday 28 November', 'West Highlands', 'Go', 'Made-up headline', 'Made-up wind 10mph', 'Made-up rain for WH', '80%'])
            ->assertSee('Full MWIS forecast');
    }

    public function test_nothing_from_the_forecast_shows_while_switched_off(): void
    {
        config(['mwis.enabled' => false]);
        $hill = $this->munro('Ben Test', 'WH');
        $this->forecast('WH', '2026-11-28', headline: 'Made-up headline');
        $me = User::factory()->create();

        foreach ([route('home'), route('weather'), route('hills.index'), route('hills.show', $hill), route('my-hills.todo')] as $url) {
            $this->actingAs($me)->get($url)
                ->assertOk()
                ->assertDontSee('Made-up')
                ->assertDontSee('data-test="weather-rating"', false)
                ->assertDontSee('MWIS forecast for');
        }

        // The area and the link to its forecast are still there.
        $this->actingAs($me)->get(route('weather'))
            ->assertSee('West Highlands')
            ->assertSee('https://www.mwis.org.uk/forecasts/scottish/west-highlands', false);
    }

    public function test_english_and_welsh_areas_have_no_rating_and_unreadable_numbers_say_see_mwis(): void
    {
        $this->actingAs(User::factory()->create());
        $this->forecast('LD', '2026-11-28');
        $this->forecast('WH', '2026-11-28', cloudFree: null);

        Livewire::test('pages::weather')
            ->assertSee('Made-up rain for LD')
            ->assertSee('See MWIS')
            ->assertDontSeeHtml('data-test="weather-rating"');
    }

    public function test_the_weather_page_counts_the_hills_left_for_me_and_for_the_group(): void
    {
        $me = User::factory()->create();
        $friend = User::factory()->create();
        $group = Group::factory()->create(['name' => 'Fife crew']);
        $group->users()->attach($me, ['role' => 'admin']);
        $group->users()->attach($friend, ['role' => 'member']);

        $mine = $this->munro('Ben Mine', 'WH');
        $theirs = $this->munro('Ben Theirs', 'WH');
        $this->munro('Ben Fresh', 'WH');
        $this->climbed($me, $mine);
        $this->climbed($friend, $theirs);

        Livewire::actingAs($me)->test('pages::weather')
            ->assertSet('group', (string) $group->id)
            ->assertSee('2 for me to do')
            ->assertSee('1 nobody in Fife crew has done')
            ->assertSee(route('my-hills.todo', ['mwis' => 'WH', 'list' => 'M']))
            ->set('group', 'none')
            ->assertDontSee('has done');
    }

    public function test_the_to_do_list_can_be_narrowed_to_an_area_and_sorted_by_weather(): void
    {
        $this->munro('Ben Windy', 'WH', ['height_m' => 1300]);
        $this->munro('Ben Calm', 'SH', ['height_m' => 920]);
        $this->munro('Ben Breezy', 'EH', ['height_m' => 1000]);
        $this->forecast('WH', '2026-11-28', wind: 60);
        $this->forecast('SH', '2026-11-28');
        $this->forecast('EH', '2026-11-28', wind: 35);

        Livewire::actingAs(User::factory()->create())->test('pages::my-hills-todo')
            ->set('sort', 'weather')
            ->assertSeeInOrder(['Ben Calm', 'Ben Breezy', 'Ben Windy'])
            ->set('mwis', 'SH')
            ->assertSee('Ben Calm')
            ->assertDontSee('Ben Windy')
            ->set('mwis', '')
            ->assertSee('Ben Windy');
    }

    public function test_the_home_page_shows_the_scottish_areas_and_my_progress(): void
    {
        $me = User::factory()->create();
        $done = $this->munro('Ben Done', 'WH');
        $this->munro('Ben Waiting', 'WH');
        $this->climbed($me, $done);
        $this->forecast('WH', '2026-11-28', headline: 'Made-up headline');
        $this->forecast('LD', '2026-11-28', headline: 'Lakes headline');

        $this->actingAs($me)->get(route('home'))
            ->assertOk()
            ->assertSee('This weekend')
            ->assertSee('Saturday 28 November')
            ->assertSee('Made-up headline')
            ->assertDontSee('Lakes headline')
            ->assertSeeInOrder(['Northwest Highlands', 'West Highlands', 'Cairngorms NP and Monadhliath', 'Southeastern Highlands', 'Southern Uplands'])
            ->assertSee('Latest: Ben Done, 1 Oct 2026')
            ->assertSee(route('weather'), false);
    }

    public function test_the_weather_page_is_for_signed_in_people(): void
    {
        $this->get(route('weather'))->assertRedirect(route('login'));
    }
}

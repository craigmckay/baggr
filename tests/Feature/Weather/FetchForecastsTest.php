<?php

namespace Tests\Feature\Weather;

use App\Enums\WeatherRating;
use App\Models\MwisForecast;
use App\Support\Mwis\ForecastPageParser;
use App\Support\Mwis\UnreadableForecast;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\FakeForecastPage;
use Tests\TestCase;

class FetchForecastsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Sleep::fake();
        Http::preventStrayRequests();
    }

    private function threeDays(): string
    {
        return FakeForecastPage::make([
            [
                'date' => 'Saturday 28 November 2026',
                'headline' => 'Bright for most of the day – “calm”.',
                'wind' => 'Westerly, 15 to 25mph, may gust 40mph on tops.',
                'cloud_free' => '30% in the west, 70% in the east',
                'rain' => 'Dry',
                'freezing' => 'About 900m',
            ],
            ['date' => 'Sunday 29 November 2026', 'wind' => 'Southerly 35mph.'],
            ['date' => 'Monday 30 November 2026'],
        ]);
    }

    public function test_the_page_is_read_into_one_entry_a_day(): void
    {
        $days = app(ForecastPageParser::class)->parse($this->threeDays());

        $this->assertSame(['2026-11-28', '2026-11-29', '2026-11-30'], array_column($days, 'date'));
        $this->assertSame('Bright for most of the day – “calm”.', $days[0]['headline']);
        $this->assertNull($days[1]['headline']);
        $this->assertSame('Westerly, 15 to 25mph, may gust 40mph on tops.', $days[0]['wind']);
        $this->assertSame(25, $days[0]['wind_mph']);
        $this->assertSame(70, $days[0]['cloud_free_percent']);
        $this->assertSame('Dry', $days[0]['rain']);
        $this->assertSame('About 900m', $days[0]['freezing_level']);
        $this->assertSame('2026-11-27 14:30:00', $days[0]['issued_at']->toDateTimeString());
    }

    /** @return array<string, array{string, ?int}> */
    public static function winds(): array
    {
        return [
            'a range' => ['40 to 55mph, occasionally 60mph, gusts 70mph', 55],
            'a dash' => ['Westerly 20-30mph', 30],
            'mph twice' => ['10mph to 20mph', 20],
            'one speed' => ['Light, 10mph or less', 10],
            'gusts before the range' => ['Gusts 60mph early, otherwise 20 to 30mph', 30],
            'no speed' => ['Light and variable', null],
        ];
    }

    #[DataProvider('winds')]
    public function test_the_wind_is_the_top_of_the_main_range(string $text, ?int $expected): void
    {
        $this->assertSame($expected, app(ForecastPageParser::class)->windMph($text));
    }

    /** @return array<string, array{?int, ?int, ?WeatherRating}> */
    public static function ratings(): array
    {
        return [
            'calm and clear' => [20, 70, WeatherRating::Go],
            'cloud at the limit' => [20, 40, WeatherRating::Go],
            'too cloudy' => [10, 39, WeatherRating::Poor],
            'breezy' => [30, 80, WeatherRating::Marginal],
            'windy at the limit' => [40, 80, WeatherRating::Marginal],
            'too windy' => [41, 80, WeatherRating::Poor],
            'no wind figure' => [null, 80, null],
            'no cloud figure' => [10, null, null],
        ];
    }

    #[DataProvider('ratings')]
    public function test_the_rating_follows_the_wind_and_the_cloud(?int $wind, ?int $cloudFree, ?WeatherRating $expected): void
    {
        $this->assertSame($expected, WeatherRating::fromNumbers($wind, $cloudFree));
    }

    /** @return array<string, array{string, list<int>}> */
    public static function ranges(): array
    {
        return [
            'a range' => ['40 to 55mph, occasionally 60mph', [40, 55]],
            'between' => ['Between 10 and 20mph', [10, 20]],
            'one speed' => ['10mph or less', [10]],
            'none' => ['Light', []],
        ];
    }

    /** @param  list<int>  $expected */
    #[DataProvider('ranges')]
    public function test_the_wind_range_is_taken_from_the_text(string $text, array $expected): void
    {
        $this->assertSame($expected, app(ForecastPageParser::class)->windSpeeds($text));
    }

    public function test_a_page_laid_out_differently_cannot_be_read(): void
    {
        $this->expectException(UnreadableForecast::class);

        app(ForecastPageParser::class)->parse('<html><body><h1>New look</h1></body></html>');
    }

    public function test_nothing_is_fetched_while_forecasts_are_switched_off(): void
    {
        Http::fake();

        $this->artisan('mwis:fetch')->expectsOutputToContain('switched off')->assertSuccessful();

        Http::assertNothingSent();
        $this->assertDatabaseCount('mwis_forecasts', 0);
    }

    public function test_the_fetch_stores_each_area_with_one_request_each(): void
    {
        config(['mwis.enabled' => true]);
        Http::fake(['www.mwis.org.uk/*' => Http::response($this->threeDays())]);

        $this->artisan('mwis:fetch')->assertSuccessful();

        Http::assertSentCount(10);
        Http::assertSent(fn (Request $request) => $request->hasHeader('User-Agent', 'Mozilla/5.0 (compatible; Baggr/1.0; +https://baggr.co.uk)'));
        $this->assertDatabaseCount('mwis_forecasts', 30);
        $this->assertSame(25, MwisForecast::query()->where('area', 'WH')->orderBy('date')->firstOrFail()->wind_mph);
    }

    public function test_past_days_are_kept_and_coming_days_are_updated_in_place(): void
    {
        config(['mwis.enabled' => true]);
        MwisForecast::create(['area' => 'WH', 'date' => '2026-11-27', 'wind' => 'Gone by']);
        $coming = MwisForecast::create(['area' => 'WH', 'date' => '2026-11-28', 'headline' => 'Old headline', 'wind' => 'Old']);
        Http::fake(['www.mwis.org.uk/*' => Http::response($this->threeDays())]);

        $this->artisan('mwis:fetch')->assertSuccessful();
        $this->artisan('mwis:fetch')->assertSuccessful();

        $held = MwisForecast::query()->where('area', 'WH')->orderBy('date')->get();

        $this->assertSame(['2026-11-27', '2026-11-28', '2026-11-29', '2026-11-30'], $held->map(fn (MwisForecast $f) => $f->date->toDateString())->all());
        $this->assertSame('Gone by', $held[0]->wind);
        $this->assertSame($coming->id, $held[1]->id);
        $this->assertSame('Westerly, 15 to 25mph, may gust 40mph on tops.', $held[1]->wind);
        $this->assertDatabaseCount('mwis_forecasts', 31);
    }

    public function test_a_failed_area_keeps_its_last_forecast_and_the_others_are_updated(): void
    {
        config(['mwis.enabled' => true]);
        MwisForecast::create(['area' => 'WH', 'date' => '2026-11-26', 'wind' => 'Kept']);
        Log::spy();
        Http::fake([
            '*/west-highlands' => Http::response('<html><body>Changed</body></html>'),
            '*/southern-uplands' => Http::response('', 500),
            '*' => Http::response($this->threeDays()),
        ]);

        $this->artisan('mwis:fetch')->assertFailed();

        $this->assertSame(['Kept'], MwisForecast::query()->where('area', 'WH')->pluck('wind')->all());
        $this->assertSame(0, MwisForecast::query()->where('area', 'SU')->count());
        $this->assertSame(3, MwisForecast::query()->where('area', 'SH')->count());
        Log::shouldHaveReceived('error')->twice();
    }

    public function test_the_fetch_is_scheduled_for_six_in_the_evening_uk_time(): void
    {
        $event = collect(app(Schedule::class)->events())
            ->first(fn (Event $event) => str_contains((string) $event->command, 'mwis:fetch'));

        $this->assertNotNull($event);
        $this->assertSame('0 18 * * *', $event->expression);
        $this->assertSame('Europe/London', (string) $event->timezone);
        $this->assertFalse($event->filtersPass(app()));

        config(['mwis.enabled' => true]);
        $this->assertTrue($event->filtersPass(app()));
    }
}

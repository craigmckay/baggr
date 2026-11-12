<?php

namespace Tests\Feature;

use App\Models\Hill;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\TestCase;

class FetchHillPhotosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.geograph.key' => 'test-key']);
        Sleep::fake();
        Http::preventStrayRequests();
    }

    public function test_it_stores_the_best_summit_photo_with_its_attribution(): void
    {
        $hill = $this->benNevis();

        Http::fake([
            'api.geograph.org.uk/*' => Http::response($this->feed([
                $this->item(8155242, 'NN1671 : Tower Gully', 'David Bremner', 'top:Uplands?type:Geograph', 56.796407, -5.007145),
                $this->item(7997996, 'NN1671 : Me on the summit of Ben Nevis', 'Philip Halling', 'type:Close Look?subject:summit', 56.796560, -5.003767),
                $this->item(8357549, 'NN1671 : Ben Nevis summit', 'Anthony Foster', 'top:Uplands?type:Geograph?subject:summit', 56.796919, -5.003796),
                $this->item(7836995, 'NN1671 : View from the summit of Ben Nevis', 'Philip Halling', 'type:Geograph?subject:mountain?view', 56.795215, -5.003659),
            ])),
        ]);

        $this->artisan('hills:fetch-photos')->assertSuccessful();

        $hill->refresh();

        $this->assertSame('https://s1.geograph.org.uk/geophotos/08/35/75/8357549_abcdef12.jpg', $hill->photo_url);
        $this->assertSame('https://www.geograph.org.uk/photo/8357549', $hill->photo_page_url);
        $this->assertSame('Ben Nevis summit', $hill->photo_title);
        $this->assertSame('Anthony Foster', $hill->photo_author);
        $this->assertEquals(8357549, $hill->photo_geograph_id);
    }

    public function test_it_queries_geograph_by_grid_reference_with_the_api_key(): void
    {
        $this->benNevis();

        Http::fake(['api.geograph.org.uk/*' => Http::response($this->feed([]))]);

        $this->artisan('hills:fetch-photos')->assertSuccessful();

        Http::assertSent(function (Request $request) {
            return str_starts_with($request->url(), 'https://api.geograph.org.uk/syndicator.php?')
                && $request['key'] === 'test-key'
                && $request['format'] === 'JSON'
                && $request['location'] === 'NN166712'
                && (string) $request['distance'] === '1'
                && str_contains($request->header('User-Agent')[0], 'Baggr');
        });
    }

    public function test_it_skips_hills_that_already_have_a_photo_unless_forced(): void
    {
        $hill = $this->benNevis(['photo_url' => 'https://s0.geograph.org.uk/geophotos/00/00/01/1_existing.jpg']);

        Http::fake([
            'api.geograph.org.uk/*' => Http::response($this->feed([
                $this->item(8357549, 'NN1671 : Ben Nevis summit', 'Anthony Foster', 'subject:summit', 56.796919, -5.003796),
            ])),
        ]);

        $this->artisan('hills:fetch-photos')->assertSuccessful();

        Http::assertNothingSent();
        $this->assertSame('https://s0.geograph.org.uk/geophotos/00/00/01/1_existing.jpg', $hill->fresh()->photo_url);

        $this->artisan('hills:fetch-photos --force')->assertSuccessful();

        Http::assertSentCount(1);
        $this->assertSame('https://s1.geograph.org.uk/geophotos/08/35/75/8357549_abcdef12.jpg', $hill->fresh()->photo_url);
    }

    public function test_it_leaves_the_photo_empty_when_geograph_has_no_images(): void
    {
        $hill = $this->benNevis();

        Http::fake(['api.geograph.org.uk/*' => Http::response($this->feed([]))]);

        $this->artisan('hills:fetch-photos')
            ->expectsOutputToContain('No photo found for: Ben Nevis')
            ->assertSuccessful();

        $this->assertNull($hill->fresh()->photo_url);
        $this->assertNull($hill->fresh()->photo_author);
    }

    public function test_it_ignores_photos_taken_far_from_the_summit(): void
    {
        $hill = $this->benNevis();

        // Geograph falls back to a place-name search for locations it can't parse.
        Http::fake([
            'api.geograph.org.uk/*' => Http::response($this->feed([
                $this->item(1234567, 'SK5023 : Summit of Ben Nevis', 'Someone Else', 'subject:summit', 52.805, -1.25),
            ])),
        ]);

        $this->artisan('hills:fetch-photos')->assertSuccessful();

        $this->assertNull($hill->fresh()->photo_url);
    }

    public function test_it_ignores_photos_that_are_not_cc_by_sa(): void
    {
        $hill = $this->benNevis();

        $item = $this->item(8357549, 'NN1671 : Ben Nevis summit', 'Anthony Foster', 'subject:summit', 56.796919, -5.003796);
        $item['licence'] = 'http://example.com/all-rights-reserved';

        Http::fake(['api.geograph.org.uk/*' => Http::response($this->feed([$item]))]);

        $this->artisan('hills:fetch-photos')->assertSuccessful();

        $this->assertNull($hill->fresh()->photo_url);
    }

    public function test_it_carries_on_after_a_failed_request_and_throttles_between_hills(): void
    {
        $first = $this->benNevis();
        $second = Hill::factory()->create(['name' => 'Carn Mor Dearg', 'grid_ref' => 'NN177721', 'latitude' => 56.8049, 'longitude' => -4.9865]);

        Http::fake([
            'api.geograph.org.uk/*location=NN166712*' => Http::response('Server error', 500),
            'api.geograph.org.uk/*location=NN177721*' => Http::response($this->feed([
                $this->item(7000001, 'NN1772 : Carn Mor Dearg summit', 'A Walker', 'subject:summit', 56.8049, -4.9865),
            ])),
        ]);

        $this->artisan('hills:fetch-photos')->assertSuccessful();

        $this->assertNull($first->fresh()->photo_url);
        $this->assertEquals(7000001, $second->fresh()->photo_geograph_id);
        Sleep::assertSleptTimes(1);
    }

    public function test_it_fails_without_an_api_key(): void
    {
        config(['services.geograph.key' => null]);
        $this->benNevis();
        Http::fake();

        $this->artisan('hills:fetch-photos')
            ->expectsOutputToContain('GEOGRAPH_API_KEY')
            ->assertFailed();

        Http::assertNothingSent();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function benNevis(array $attributes = []): Hill
    {
        return Hill::factory()->create([
            'name' => 'Ben Nevis',
            'grid_ref' => 'NN166712',
            'latitude' => 56.796849,
            'longitude' => -5.003525,
            ...$attributes,
        ]);
    }

    /**
     * A feed in the shape returned by Geograph's syndicator (checked
     * against a real response on 2026-11-12).
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array<string, mixed>
     */
    private function feed(array $items): array
    {
        return [
            'generator' => 'FeedCreator 1.7.12(BH)',
            'title' => 'Geograph Britain and Ireland',
            'description' => 'Images, in grid reference NN1671',
            'link' => 'https://www.geograph.org.uk/search.php?i=1',
            'items' => $items,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function item(int $id, string $title, string $author, string $tags, float $lat, float $long): array
    {
        $path = implode('/', str_split(str_pad((string) intdiv($id, 100), 6, '0', STR_PAD_LEFT), 2));
        $thumb = "https://s1.geograph.org.uk/geophotos/{$path}/{$id}_abcdef12_120x120.jpg";

        return [
            'title' => $title,
            'link' => "https://www.geograph.org.uk/photo/{$id}",
            'author' => $author,
            'guid' => (string) $id,
            'source' => 'https://www.geograph.org.uk/profile/1',
            'date' => 1783463028,
            'imageTaken' => '2026-07-04',
            'tags' => $tags,
            'lat' => (string) $lat,
            'long' => (string) $long,
            'thumb' => $thumb,
            'thumbTag' => "<img alt=\"{$title} by {$author}\" src=\"{$thumb}\" width=\"120\" height=\"90\" />",
            'licence' => 'http://creativecommons.org/licenses/by-sa/2.0/',
        ];
    }
}

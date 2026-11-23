<?php

namespace Tests\Feature\MyHills;

use App\Actions\Walks\LogWalk;
use App\Enums\HillGrouping;
use App\Models\Area;
use App\Models\Group;
use App\Models\Hill;
use App\Models\HillClass;
use App\Models\User;
use App\Support\HillProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HillProgressTest extends TestCase
{
    use RefreshDatabase;

    private function onList(Hill $hill, string $code): Hill
    {
        $class = HillClass::firstOrCreate(['code' => $code], ['name' => $code]);
        $hill->classes()->attach($class);

        return $hill;
    }

    /** @param  array<string, mixed>  $attributes */
    private function munro(array $attributes = []): Hill
    {
        return $this->onList(Hill::factory()->create($attributes), 'M');
    }

    /** @param  list<int>  $hills */
    private function walk(User $user, array $hills, ?Group $group = null, string $date = '2026-10-01'): void
    {
        app(LogWalk::class)->handle($user, [
            'group_id' => $group?->id,
            'people' => [$user->id],
            'days' => [['date' => $date, 'direction' => null, 'duration_minutes' => null, 'hills' => $hills]],
        ]);
    }

    private function groupFor(User $user, string $colour = '#2F6F9F'): Group
    {
        $group = Group::factory()->create(['colour' => $colour]);
        $group->users()->attach($user, ['role' => 'member']);

        return $group;
    }

    public function test_it_counts_the_hills_done_out_of_the_hills_on_the_list(): void
    {
        $me = User::factory()->create();
        [$a, $b, $c] = [$this->munro(), $this->munro(), $this->munro()];
        $notAMunro = $this->onList(Hill::factory()->create(), 'C');

        $this->walk($me, [$a->id, $b->id]);
        $this->walk($me, [$notAMunro->id], date: '2026-10-02');

        $progress = new HillProgress($me, 'M');

        $this->assertSame(3, $progress->total());
        $this->assertSame(2, $progress->doneCount());
        $this->assertSame(66, $progress->percentage());
        $this->assertNotNull($c->id);
    }

    public function test_a_hill_climbed_twice_counts_once(): void
    {
        $me = User::factory()->create();
        $hill = $this->munro();

        $this->walk($me, [$hill->id], date: '2026-09-01');
        $this->walk($me, [$hill->id], date: '2026-10-01');

        $this->assertSame(1, (new HillProgress($me, 'M'))->doneCount());
    }

    public function test_other_peoples_ascents_do_not_count(): void
    {
        $me = User::factory()->create();
        $other = User::factory()->create();
        $hill = $this->munro();

        $this->walk($other, [$hill->id]);

        $this->assertSame(0, (new HillProgress($me, 'M'))->doneCount());
    }

    public function test_an_unknown_list_falls_back_to_the_first_list(): void
    {
        $this->assertSame('M', (new HillProgress(User::factory()->create(), 'nonsense'))->list());
    }

    public function test_a_solo_hill_takes_my_colour_and_a_group_hill_takes_the_groups(): void
    {
        $me = User::factory()->create(['colour' => '#C0392B']);
        $group = $this->groupFor($me, '#2F6F9F');
        $solo = $this->munro(['name' => 'Solo Hill']);
        $together = $this->munro(['name' => 'Group Hill']);

        $this->walk($me, [$solo->id]);
        $this->walk($me, [$together->id], $group);

        $rows = collect((new HillProgress($me, 'M'))->sections(HillGrouping::Mwis)[0]['hills'])
            ->keyBy(fn (array $row) => $row['hill']->name);

        $this->assertSame('#C0392B', $rows['Solo Hill']['colour']);
        $this->assertSame('#2F6F9F', $rows['Group Hill']['colour']);
    }

    public function test_the_first_ascent_decides_the_colour(): void
    {
        $me = User::factory()->create(['colour' => '#C0392B']);
        $group = $this->groupFor($me, '#2F6F9F');
        $hill = $this->munro();

        $this->walk($me, [$hill->id], date: '2026-08-01');
        $this->walk($me, [$hill->id], $group, date: '2026-09-01');

        $this->assertSame('#C0392B', (new HillProgress($me, 'M'))->sections(HillGrouping::Mwis)[0]['hills'][0]['colour']);
    }

    public function test_the_solo_scope_only_counts_walks_with_no_group(): void
    {
        $me = User::factory()->create();
        $group = $this->groupFor($me);
        $solo = $this->munro();
        $together = $this->munro();

        $this->walk($me, [$solo->id]);
        $this->walk($me, [$together->id], $group);

        $this->assertSame(2, (new HillProgress($me, 'M', 'all'))->doneCount());
        $this->assertSame(1, (new HillProgress($me, 'M', 'solo'))->doneCount());
    }

    public function test_a_group_scope_only_counts_that_groups_walks(): void
    {
        $me = User::factory()->create();
        $one = $this->groupFor($me);
        $two = $this->groupFor($me);
        $a = $this->munro();
        $b = $this->munro();
        $c = $this->munro();

        $this->walk($me, [$a->id], $one);
        $this->walk($me, [$b->id], $two);
        $this->walk($me, [$c->id]);

        $progress = new HillProgress($me, 'M', (string) $one->id);

        $this->assertSame(1, $progress->doneCount());
        $this->assertSame((string) $one->id, $progress->scope());
    }

    public function test_a_group_i_am_not_in_falls_back_to_all_ascents(): void
    {
        $me = User::factory()->create();
        $stranger = User::factory()->create();
        $theirs = $this->groupFor($stranger);
        $hill = $this->munro();

        $this->walk($me, [$hill->id]);

        $progress = new HillProgress($me, 'M', (string) $theirs->id);

        $this->assertSame('all', $progress->scope());
        $this->assertSame(1, $progress->doneCount());
    }

    public function test_mwis_sections_follow_the_areas_order_and_unmapped_hills_come_last(): void
    {
        $me = User::factory()->create();
        $this->munro(['name' => 'Cairn One', 'mwis_forecast_url' => 'https://www.mwis.org.uk/forecasts/scottish/cairngorms-np-and-monadhliath']);
        $this->munro(['name' => 'West One', 'mwis_forecast_url' => 'https://www.mwis.org.uk/forecasts/scottish/west-highlands']);
        $this->munro(['name' => 'Unmapped One', 'mwis_forecast_url' => null]);

        $labels = array_column((new HillProgress($me, 'M'))->sections(HillGrouping::Mwis), 'label');

        $this->assertSame(['West Highlands', 'Cairngorms NP and Monadhliath', 'No MWIS area'], $labels);
    }

    public function test_smc_sections_are_numbered_and_in_numeric_order(): void
    {
        $me = User::factory()->create();
        $this->munro(['smc_section' => 10, 'smc_section_description' => 'Ten']);
        $this->munro(['smc_section' => 2, 'smc_section_description' => 'Two']);

        $labels = array_column((new HillProgress($me, 'M'))->sections(HillGrouping::Smc), 'label');

        $this->assertSame(['2. Two', '10. Ten'], $labels);
    }

    public function test_a_hill_with_no_smc_section_is_not_mapped_yet(): void
    {
        $me = User::factory()->create();
        $this->munro(['smc_section' => null, 'smc_section_description' => null]);

        $this->assertSame(['Not mapped yet'], array_column((new HillProgress($me, 'M'))->sections(HillGrouping::Smc), 'label'));
    }

    public function test_hills_in_a_section_are_tallest_first_and_counted(): void
    {
        $me = User::factory()->create();
        $low = $this->munro(['name' => 'Low', 'height_m' => 920]);
        $this->munro(['name' => 'High', 'height_m' => 1200]);

        $this->walk($me, [$low->id]);

        $section = (new HillProgress($me, 'M'))->sections(HillGrouping::Mwis)[0];

        $this->assertSame(['High', 'Low'], array_map(fn (array $row) => $row['hill']->name, $section['hills']));
        $this->assertSame(1, $section['done']);
        $this->assertSame(2, $section['total']);
    }

    public function test_the_summary_shows_every_list_whatever_is_selected(): void
    {
        $me = User::factory()->create();
        $munro = $this->munro();
        $corbett = $this->onList(Hill::factory()->create(), 'C');
        $this->onList(Hill::factory()->create(), 'C');

        $this->walk($me, [$munro->id, $corbett->id]);

        $summary = (new HillProgress($me, 'M'))->summary();

        $this->assertSame(['name' => 'Munros', 'done' => 1, 'total' => 1], $summary['M']);
        $this->assertSame(['name' => 'Corbetts', 'done' => 1, 'total' => 2], $summary['C']);
        $this->assertSame(0, $summary['Ma']['total']);
    }

    public function test_an_area_narrows_the_list_and_the_totals(): void
    {
        $me = User::factory()->create();
        $ochil = Area::create(['code' => 'D002', 'short_name' => 'D002', 'name' => 'Ochil Hills', 'type' => 'region', 'country' => 'S']);
        $in = $this->munro();
        $in->areas()->attach($ochil);
        $this->munro();
        $this->walk($me, [$in->id]);

        $progress = new HillProgress($me, 'M', 'all', 'D002');

        $this->assertSame('D002', $progress->area());
        $this->assertSame(1, $progress->total());
        $this->assertSame(1, $progress->doneCount());
        $this->assertSame(['name' => 'Munros', 'done' => 1, 'total' => 1], $progress->summary()['M']);
    }

    public function test_an_area_that_does_not_exist_is_ignored(): void
    {
        $me = User::factory()->create();
        $this->munro();

        $progress = new HillProgress($me, 'M', 'all', 'D999');

        $this->assertSame('', $progress->area());
        $this->assertSame(1, $progress->total());
    }

    public function test_a_person_with_no_ascents_has_nothing_done(): void
    {
        $this->munro();
        $progress = new HillProgress(User::factory()->create(), 'M');

        $this->assertSame(0, $progress->doneCount());
        $this->assertSame(0, $progress->percentage());
        $this->assertSame(0, $progress->summary()['M']['done']);
    }
}

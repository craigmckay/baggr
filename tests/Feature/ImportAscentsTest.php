<?php

namespace Tests\Feature;

use App\Models\Ascent;
use App\Models\Group;
use App\Models\Hill;
use App\Models\User;
use App\Models\Walk;
use App\Support\AscentImporter;
use DateTime;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Tests\TestCase;

class ImportAscentsTest extends TestCase
{
    use RefreshDatabase;

    private User $stewart;

    private User $craig;

    private User $ethan;

    private Group $mgb;

    /** @var array<int, Hill> running number => hill */
    private array $hills = [];

    private string $file;

    protected function setUp(): void
    {
        parent::setUp();

        $this->stewart = User::factory()->create(['name' => 'Stewart Duthie']);
        $this->craig = User::factory()->create(['name' => 'Craig McKay']);
        $this->ethan = User::factory()->create(['name' => 'Ethan']);
        $this->mgb = Group::factory()->create(['name' => 'Mountain Goats Bourich']);
        $this->mgb->users()->attach($this->craig, ['role' => 'admin']);
        $this->mgb->users()->attach($this->stewart, ['role' => 'member']);
        $this->mgb->users()->attach($this->ethan, ['role' => 'member']);

        foreach ([1 => 'Ben A', 2 => 'Ben B', 3 => 'Ben C', 4 => 'Ben D', 5 => 'Ben E'] as $running => $name) {
            $this->hills[$running] = Hill::factory()->create([
                'name' => $name,
                'slug' => strtolower(str_replace(' ', '-', $name)),
                'dobih_number' => 100 + $running,
            ]);
        }
    }

    protected function tearDown(): void
    {
        if (isset($this->file) && is_file($this->file)) {
            unlink($this->file);
        }

        parent::tearDown();
    }

    /**
     * A workbook like munrotab's: Munros (running number to DoBIH number) and Climbed.
     *
     * @param  list<array{int, int, string, int, string}>  $climbed  running number, climber, date, count, group
     */
    private function workbook(array $climbed): string
    {
        $book = new Spreadsheet;
        $munros = $book->getActiveSheet()->setTitle('Munros');
        $munros->fromArray([['RunningNo', 'DoBIH Number', 'Name']], null, 'A1');

        foreach ($this->hills as $running => $hill) {
            $munros->fromArray([[$running, $hill->dobih_number, $hill->name]], null, 'A'.($running + 1));
        }

        $sheet = $book->createSheet()->setTitle('Climbed');
        $sheet->fromArray([['RunningNo', 'Munro Name Height', 'ClimberID', 'Date Climbed', 'Climb Count', 'Group']], null, 'A1');

        foreach ($climbed as $index => [$running, $climber, $date, $count, $group]) {
            $sheet->fromArray([[$running, 'x', $climber, Date::PHPToExcel(new DateTime($date)), $count, $group]], null, 'A'.($index + 2));
        }

        $this->file = tempnam(sys_get_temp_dir(), 'munrotab').'.xlsx';
        IOFactory::createWriter($book, 'Xlsx')->save($this->file);

        return $this->file;
    }

    /** @return array<string, mixed> */
    private function importOptions(array $more = []): array
    {
        return [
            '--climber' => ['1=Stewart Duthie', '2=Craig McKay'],
            '--group' => ['MGB=Mountain Goats Bourich'],
        ] + $more;
    }

    /** @return list<array{int, int, string, int, string}> */
    private function history(): array
    {
        return [
            [1, 1, '2009-06-22', 1, 'JSD'],   // Stewart, solo
            [2, 1, '2020-09-26', 1, 'JSD'],   // a two-day trip
            [3, 1, '2020-09-27', 1, 'JSD'],
            [4, 2, '2020-08-08', 5, 'CAM'],   // Craig, solo, climbed five times
            [3, 1, '2025-06-26', 1, 'MGB'],   // MGB, both
            [1, 1, '2025-06-26', 1, 'MGB'],
            [3, 2, '2025-06-26', 1, 'MGB'],
            [1, 2, '2025-06-26', 1, 'MGB'],
            [5, 2, '2025-06-26', 1, 'MGB'],   // Craig only
            [1, 1, '2026-07-18', 1, 'MGB'],   // Stewart climbs Ben A again
        ];
    }

    public function test_a_dry_run_shows_the_totals_and_changes_nothing(): void
    {

        $this->artisan('ascents:import', $this->importOptions(['file' => $this->workbook($this->history()), '--dry-run' => true]))
            ->expectsOutputToContain('Dry run: nothing was changed.')
            ->expectsOutputToContain('Stewart Duthie')
            ->assertSuccessful();

        $this->assertDatabaseCount('walks', 0);
        $this->assertDatabaseCount('ascents', 0);
    }

    public function test_solo_rows_become_solo_walks_owned_by_the_person(): void
    {
        $this->artisan('ascents:import', $this->importOptions(['file' => $this->workbook($this->history())]))->assertSuccessful();

        $solo = Walk::whereNull('group_id')->get();

        $this->assertCount(3, $solo);
        $this->assertEqualsCanonicalizing(
            [$this->stewart->id, $this->stewart->id, $this->craig->id],
            $solo->pluck('organiser_id')->all(),
        );
        $this->assertTrue($solo->every(fn (Walk $walk) => $walk->notes === AscentImporter::MARKER && $walk->status->value === 'completed'));
    }

    public function test_a_walk_is_titled_with_its_hills_in_order(): void
    {
        $this->artisan('ascents:import', $this->importOptions(['file' => $this->workbook($this->history())]))->assertSuccessful();

        $titles = Walk::pluck('title')->all();

        $this->assertContains('Ben A, Ben C and Ben E', $titles, 'the MGB day, in running-number order');
        $this->assertContains('Ben B and Ben C', $titles, 'the two-day trip lists both days');
        $this->assertContains('Ben A', $titles);
    }

    public function test_consecutive_dates_become_one_multi_day_walk(): void
    {
        $this->artisan('ascents:import', $this->importOptions(['file' => $this->workbook($this->history())]))->assertSuccessful();

        $trip = Walk::whereHas('days', fn ($d) => $d->whereDate('date', '2020-09-26'))->firstOrFail();

        $this->assertSame(['2020-09-26', '2020-09-27'], $trip->days->map(fn ($day) => $day->date->toDateString())->all());
        $this->assertSame([1, 2], $trip->days->pluck('day_number')->all());
    }

    public function test_a_group_walk_ticks_each_hill_for_the_people_who_did_it(): void
    {
        $this->artisan('ascents:import', $this->importOptions(['file' => $this->workbook($this->history())]))->assertSuccessful();

        $walk = Walk::where('group_id', $this->mgb->id)->whereHas('days', fn ($d) => $d->whereDate('date', '2025-06-26'))->firstOrFail();
        $day = $walk->days->first();

        $this->assertSame($this->craig->id, $walk->organiser_id);
        $this->assertEqualsCanonicalizing([$this->craig->id, $this->stewart->id], $walk->attendees()->pluck('user_id')->all());
        $this->assertSame(
            [$this->hills[1]->id, $this->hills[3]->id, $this->hills[5]->id],
            $day->hills()->orderBy('position')->pluck('hills.id')->all(),
            'hills are in running-number order',
        );
        $this->assertTrue(Ascent::where(['user_id' => $this->stewart->id, 'hill_id' => $this->hills[1]->id, 'walk_day_id' => $day->id])->exists());
        $this->assertFalse(Ascent::where(['user_id' => $this->stewart->id, 'hill_id' => $this->hills[5]->id, 'walk_day_id' => $day->id])->exists());
        $this->assertTrue(Ascent::where(['user_id' => $this->craig->id, 'hill_id' => $this->hills[5]->id, 'walk_day_id' => $day->id])->exists());
    }

    public function test_a_group_walk_with_no_admin_on_it_is_logged_by_a_member_who_was(): void
    {
        $this->artisan('ascents:import', $this->importOptions(['file' => $this->workbook($this->history())]))->assertSuccessful();

        $walk = Walk::where('group_id', $this->mgb->id)->whereHas('days', fn ($d) => $d->whereDate('date', '2026-07-18'))->firstOrFail();

        $this->assertSame($this->stewart->id, $walk->organiser_id);
    }

    public function test_a_hill_climbed_several_times_with_one_date_gets_one_ascent_and_a_note(): void
    {
        $this->artisan('ascents:import', $this->importOptions(['file' => $this->workbook($this->history())]))->assertSuccessful();

        $ascents = Ascent::where(['user_id' => $this->craig->id, 'hill_id' => $this->hills[4]->id])->get();

        $this->assertCount(1, $ascents);
        $this->assertSame('Climbed 5 times; only this date recorded', $ascents->first()->notes);
    }

    public function test_repeat_ascents_are_kept_and_the_first_counts(): void
    {
        $this->artisan('ascents:import', $this->importOptions(['file' => $this->workbook($this->history())]))->assertSuccessful();

        $ascents = Ascent::where(['user_id' => $this->stewart->id, 'hill_id' => $this->hills[1]->id])->with('walkDay')->get();

        $this->assertCount(3, $ascents);
        $this->assertSame('2009-06-22', $ascents->map(fn (Ascent $a) => $a->walkDay->date->toDateString())->sort()->first());
    }

    public function test_someone_can_be_added_to_a_group_walk_by_hand(): void
    {
        $this->artisan('ascents:import', $this->importOptions([
            'file' => $this->workbook($this->history()),
            '--add' => ['MGB 2025-06-26=Ethan'],
        ]))->assertSuccessful();

        $walk = Walk::where('group_id', $this->mgb->id)->whereHas('days', fn ($d) => $d->whereDate('date', '2025-06-26'))->firstOrFail();

        $this->assertContains($this->ethan->id, $walk->attendees()->pluck('user_id')->all());
        $this->assertSame(3, Ascent::where('user_id', $this->ethan->id)->count());
        $this->assertSame(0, Ascent::where('user_id', $this->ethan->id)->whereHas('walkDay', fn ($d) => $d->whereDate('date', '2026-07-18'))->count());
    }

    public function test_adding_someone_to_a_walk_that_does_not_exist_stops_everything(): void
    {
        $this->artisan('ascents:import', $this->importOptions([
            'file' => $this->workbook($this->history()),
            '--add' => ['MGB 2024-01-01=Ethan'],
        ]))->expectsOutputToContain('There is no MGB walk on 2024-01-01')->assertFailed();

        $this->assertDatabaseCount('walks', 0);
    }

    public function test_running_it_again_adds_nothing(): void
    {
        $file = $this->workbook($this->history());
        $this->artisan('ascents:import', $this->importOptions(['file' => $file]))->assertSuccessful();
        $walks = Walk::count();
        $ascents = Ascent::count();

        $this->artisan('ascents:import', $this->importOptions(['file' => $file]))
            ->expectsOutputToContain('Added 0 walks')
            ->assertSuccessful();

        $this->assertSame($walks, Walk::count());
        $this->assertSame($ascents, Ascent::count());
    }

    public function test_rollback_removes_only_what_was_imported(): void
    {
        $mine = Walk::create(['group_id' => null, 'status' => 'completed', 'organiser_id' => $this->craig->id, 'notes' => 'My own walk']);
        $this->artisan('ascents:import', $this->importOptions(['file' => $this->workbook($this->history())]))->assertSuccessful();

        $this->artisan('ascents:import', ['--rollback' => true])->expectsOutputToContain('imported walks removed')->assertSuccessful();

        $this->assertSame([$mine->id], Walk::pluck('id')->all());
        $this->assertDatabaseCount('ascents', 0);
    }

    public function test_a_name_that_matches_no_account_stops_before_anything_is_added(): void
    {
        $this->artisan('ascents:import', [
            'file' => $this->workbook($this->history()),
            '--climber' => ['1=Stewart Nobody', '2=Craig McKay'],
            '--group' => ['MGB=Mountain Goats Bourich'],
        ])->expectsOutputToContain('0 accounts are named "Stewart Nobody"')->assertFailed();

        $this->assertDatabaseCount('walks', 0);
    }

    public function test_a_name_that_matches_two_accounts_stops_too(): void
    {
        User::factory()->create(['name' => 'Craig McKay']);

        $this->artisan('ascents:import', $this->importOptions(['file' => $this->workbook($this->history())]))
            ->expectsOutputToContain('2 accounts are named "Craig McKay"')
            ->assertFailed();

        $this->assertDatabaseCount('walks', 0);
    }

    public function test_the_importer_totals_match_the_workbook(): void
    {
        $this->artisan('ascents:import', $this->importOptions(['file' => $this->workbook($this->history()), '--dry-run' => true]))
            ->expectsOutputToContain('Stewart Duthie')
            ->expectsOutputToContain('Craig McKay')
            ->assertSuccessful();
    }
}

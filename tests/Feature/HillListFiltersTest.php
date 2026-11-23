<?php

namespace Tests\Feature;

use App\Actions\Walks\LogWalk;
use App\Enums\HillGrouping;
use App\Models\Area;
use App\Models\Group;
use App\Models\Hill;
use App\Models\HillClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class HillListFiltersTest extends TestCase
{
    use RefreshDatabase;

    /** @param  array<string, mixed>  $attributes */
    private function hill(string $name, array $attributes = [], string $list = 'M'): Hill
    {
        $hill = Hill::factory()->create(['name' => $name, 'mwis_forecast_url' => null] + $attributes);
        $hill->classes()->attach(HillClass::firstOrCreate(['code' => $list], ['name' => $list, 'show_in_list' => true, 'sort_order' => 1]));

        return $hill;
    }

    private function inArea(Hill $hill, string $code, string $name, string $type = 'hill_range'): void
    {
        $hill->areas()->attach(Area::firstOrCreate(['code' => $code], ['name' => $name, 'short_name' => $code, 'type' => $type, 'country' => 'S']));
    }

    private function walk(User $user, Hill $hill, ?Group $group = null): void
    {
        app(LogWalk::class)->handle($user, [
            'group_id' => $group?->id,
            'people' => [$user->id],
            'days' => [['date' => '2026-10-01', 'direction' => null, 'duration_minutes' => null, 'hills' => [$hill->id]]],
        ]);
    }

    public function test_the_area_filter_narrows_the_list_and_combines_with_the_list_selector(): void
    {
        $ochil = $this->hill('Ben Cleuch', [], 'Ma');
        $ochilGraham = $this->hill('Dumyat', [], 'G');
        $elsewhere = $this->hill('Ben Lomond', [], 'Ma');
        $this->inArea($ochil, 'D002', 'Ochil Hills');
        $this->inArea($ochilGraham, 'D002', 'Ochil Hills');

        Livewire::test('pages::hills')
            ->set('list', 'all')
            ->set('area', 'D002')
            ->assertSee('Ben Cleuch')
            ->assertSee('Dumyat')
            ->assertDontSee('Ben Lomond')
            ->set('list', 'Ma')
            ->assertSee('Ben Cleuch')
            ->assertDontSee('Dumyat')
            ->assertSee('Ochil Hills');

        $this->assertNotNull($elsewhere->id);
    }

    public function test_the_kind_of_area_is_chosen_before_the_area(): void
    {
        $this->inArea($this->hill('Ben Cleuch'), 'D002', 'Ochil Hills');
        $this->inArea($this->hill('Ben Lomond'), 'M01C', '01C: Loch Lomond to Strathyre', 'murray_section');

        $this->get(route('hills.index'))
            ->assertSee('>Hill range</option>', false)
            ->assertSee('>Murray sub-section</option>', false)
            ->assertDontSee('Ochil Hills (1)', false);

        Livewire::test('pages::hills')
            ->set('areaCategory', 'hill_range')
            ->assertSee('Ochil Hills (1)')
            ->assertSee('Hill range: any')
            ->assertDontSee('Loch Lomond to Strathyre')
            ->set('area', 'D002')
            ->set('areaCategory', 'murray_section')
            ->assertSet('area', '')
            ->assertSee('01C: Loch Lomond to Strathyre (1)');
    }

    public function test_an_area_in_the_address_opens_with_its_kind_chosen(): void
    {
        $this->inArea($this->hill('Ben Cleuch'), 'D002', 'Ochil Hills');

        Livewire::withQueryParams(['area' => 'D002'])->test('pages::hills')
            ->assertSet('areaCategory', 'hill_range')
            ->assertSee('Ochil Hills (1)');
    }

    public function test_search_matches_the_start_of_a_word_not_the_middle(): void
    {
        $this->hill('Dumyat');
        $this->hill('Ben Dum Fell');
        $this->hill('Meldum Hill');

        Livewire::test('pages::hills')
            ->set('search', 'Dum')
            ->assertSee('Dumyat')
            ->assertSee('Ben Dum Fell')
            ->assertDontSee('Meldum Hill');
    }

    public function test_a_short_search_does_not_bring_in_whole_areas(): void
    {
        $this->inArea($this->hill('Ben Nowhere'), 'A005', 'Dumfries and Galloway', 'authority');
        $this->hill('Dumyat');

        Livewire::test('pages::hills')
            ->set('search', 'Dum')
            ->assertSee('Dumyat')
            ->assertDontSee('Ben Nowhere')
            ->set('search', 'Dumf')
            ->assertSee('Ben Nowhere')
            ->assertDontSee('Dumyat');
    }

    public function test_every_hill_shows_its_mwis_area_as_a_link_to_the_forecast(): void
    {
        $this->hill('Ben Cleuch', ['mwis_area' => 'SH']);
        $this->hill('Irish Hill', []);

        $this->get(route('hills.index'))
            ->assertSee('https://www.mwis.org.uk/forecasts/scottish/southeastern-highlands', false)
            ->assertSee('>SH<', false);
    }

    public function test_the_list_can_be_grouped_with_each_section_headed(): void
    {
        $this->hill('Ben Z West', ['mwis_area' => 'WH']);
        $this->hill('Ben A North', ['mwis_area' => 'NW']);
        $this->hill('Ben Nowhere', ['mwis_area' => null]);

        Livewire::test('pages::hills')
            ->set('groupBy', 'mwis')
            ->assertSeeInOrder(['Northwest Highlands', 'Ben A North', 'West Highlands', 'Ben Z West', 'No MWIS area', 'Ben Nowhere']);
    }

    public function test_grouping_by_murray_section_works_for_hills_that_are_not_munros(): void
    {
        $this->hill('Wee Marilyn', ['dobih_section' => '26.1'], 'Ma');
        Area::create(['code' => 'M26', 'short_name' => 'M26', 'name' => '26: Central Scotland from Dumbarton to Montrose', 'type' => 'murray_section', 'country' => 'S']);
        Area::create(['code' => 'M26A', 'short_name' => 'M26A', 'name' => '26A: Central Scotland from Dumbarton to Montrose', 'type' => 'murray_section', 'country' => 'S']);

        $section = HillGrouping::MurraySection->sectionFor(Hill::firstWhere('name', 'Wee Marilyn'));
        $subsection = HillGrouping::MurraySubsection->sectionFor(Hill::firstWhere('name', 'Wee Marilyn'));

        $this->assertSame('26. Central Scotland from Dumbarton to Montrose', $section['label']);
        $this->assertSame('26A. Central Scotland from Dumbarton to Montrose', $subsection['label']);
    }

    public function test_a_hill_with_no_section_is_not_mapped_yet(): void
    {
        $hill = $this->hill('Mystery', ['dobih_section' => null]);

        $this->assertSame('Not mapped yet', HillGrouping::MurraySection->sectionFor($hill)['label']);
        $this->assertSame('Not mapped yet', HillGrouping::MurraySubsection->sectionFor($hill)['label']);
    }

    public function test_sections_are_listed_in_order_when_grouped_by_murray_section(): void
    {
        $this->hill('Ben Ten', ['dobih_section' => '10.1']);
        $this->hill('Ben Two', ['dobih_section' => '2.1']);

        Livewire::test('pages::hills')
            ->set('groupBy', 'murray')
            ->assertSeeInOrder(['Ben Two', 'Ben Ten']);
    }

    public function test_choosing_a_grouping_is_remembered_for_my_hills(): void
    {
        $me = User::factory()->create();

        Livewire::actingAs($me)->test('pages::hills')->set('groupBy', 'murray-sub');

        $this->assertSame('murray-sub', $me->fresh()->hill_grouping);
    }

    public function test_a_made_up_grouping_is_dropped(): void
    {
        $me = User::factory()->create();

        Livewire::actingAs($me)->test('pages::hills')->set('groupBy', 'nonsense')->assertSet('groupBy', '');

        $this->assertSame('mwis', $me->fresh()->hill_grouping);
    }

    public function test_the_height_filter_keeps_hills_between_the_limits(): void
    {
        $this->hill('Ben Low', ['height_m' => 920]);
        $this->hill('Ben Middle', ['height_m' => 1000]);
        $this->hill('Ben High', ['height_m' => 1200]);

        Livewire::test('pages::hills')
            ->set('minHeight', '950')
            ->set('maxHeight', '1100')
            ->assertSee('Ben Middle')
            ->assertDontSee('Ben Low')
            ->assertDontSee('Ben High')
            ->set('maxHeight', '')
            ->assertSee('Ben High');
    }

    public function test_a_height_that_is_not_a_number_is_ignored(): void
    {
        $this->hill('Ben Low', ['height_m' => 920]);

        Livewire::test('pages::hills')->set('minHeight', 'tall')->assertSee('Ben Low');
    }

    public function test_done_by_me_and_not_done_by_me(): void
    {
        $me = User::factory()->create();
        $done = $this->hill('Ben Done');
        $this->hill('Ben Waiting');
        $this->walk($me, $done);

        Livewire::actingAs($me)->test('pages::hills')
            ->set('status', 'done')
            ->assertSee('Ben Done')
            ->assertDontSee('Ben Waiting')
            ->set('status', 'todo')
            ->assertSee('Ben Waiting')
            ->assertDontSee('Ben Done');
    }

    public function test_someone_elses_ascents_do_not_make_a_hill_done_by_me(): void
    {
        $me = User::factory()->create();
        $other = User::factory()->create();
        $hill = $this->hill('Ben Theirs');
        $this->walk($other, $hill);

        Livewire::actingAs($me)->test('pages::hills')->set('status', 'todo')->assertSee('Ben Theirs');
    }

    public function test_not_done_by_anyone_in_a_group(): void
    {
        $me = User::factory()->create();
        $friend = User::factory()->create();
        $group = Group::factory()->create();
        $group->users()->attach($me, ['role' => 'member']);
        $group->users()->attach($friend, ['role' => 'member']);

        $myHill = $this->hill('Ben Mine');
        $friendsHill = $this->hill('Ben Friends');
        $this->hill('Ben Fresh');
        $this->walk($me, $myHill);
        $this->walk($friend, $friendsHill);

        Livewire::actingAs($me)->test('pages::hills')
            ->set('status', "group-{$group->id}")
            ->assertSee('Ben Fresh')
            ->assertDontSee('Ben Mine')
            ->assertDontSee('Ben Friends');
    }

    public function test_a_group_i_am_not_in_changes_nothing(): void
    {
        $me = User::factory()->create();
        $theirs = Group::factory()->create();
        $hill = $this->hill('Ben Done');
        $this->walk($me, $hill);

        Livewire::actingAs($me)->test('pages::hills')
            ->set('status', "group-{$theirs->id}")
            ->assertSee('Ben Done');
    }

    public function test_guests_do_not_get_the_done_filters(): void
    {
        $this->hill('Ben Open');

        $this->get(route('hills.index'))->assertDontSee('Not done by me');

        Livewire::test('pages::hills')->set('status', 'done')->assertSee('Ben Open');
    }

    public function test_signed_in_people_get_the_done_filters_with_their_groups(): void
    {
        $me = User::factory()->create();
        $group = Group::factory()->create(['name' => 'Fife Crew']);
        $group->users()->attach($me, ['role' => 'member']);

        $this->actingAs($me)->get(route('hills.index'))
            ->assertSee('Not done by me')
            ->assertSee('Not done by anyone in Fife Crew');
    }

    public function test_filters_can_be_cleared(): void
    {
        $this->hill('Ben Low', ['height_m' => 920]);

        Livewire::test('pages::hills')
            ->set('minHeight', '1000')
            ->assertDontSee('Ben Low')
            ->call('clearFilters')
            ->assertSet('minHeight', '')
            ->assertSee('Ben Low');
    }

    public function test_changing_a_filter_goes_back_to_the_first_page(): void
    {
        foreach (range(1, 60) as $i) {
            $this->hill(sprintf('Ben %02d', $i), ['slug' => "ben-{$i}", 'dobih_number' => 7000 + $i]);
        }

        Livewire::test('pages::hills')
            ->call('setPage', 2)
            ->set('minHeight', '900')
            ->assertSet('paginators.page', 1);
    }

    public function test_the_list_column_shows_the_most_significant_list_the_hill_is_on(): void
    {
        foreach (['Ma' => 'Marilyn', 'MT' => 'Munro Top', 'G' => 'Graham'] as $code => $name) {
            HillClass::create(['code' => $code, 'name' => $name, 'show_in_list' => true, 'sort_order' => 1]);
        }
        $both = $this->hill('Ben Both', [], 'Ma');
        $both->classes()->attach(HillClass::where('code', 'MT')->first());
        $this->hill('Ben Graham', [], 'G');
        $this->hill('Ben Plain', [], 'Other');

        Livewire::test('pages::hills')
            ->set('list', 'all')
            ->assertSeeHtml('hidden sm:inline">Munro Top</span>')
            ->assertSeeHtml('hidden sm:inline">Graham</span>')
            ->assertSeeHtml('<span class="sm:hidden" title="Munro Top">MT</span>')
            ->assertDontSeeHtml('>Marilyn</span>');
    }

    public function test_the_list_column_sorts_by_significance(): void
    {
        $this->hill('Ben Marilyn', [], 'Ma');
        $this->hill('Ben Munro', [], 'M');
        $this->hill('Ben Corbett', [], 'C');

        Livewire::test('pages::hills')
            ->set('list', 'all')
            ->call('sort', 'list')
            ->assertSeeInOrder(['Ben Munro', 'Ben Corbett', 'Ben Marilyn'])
            ->call('sort', 'list')
            ->assertSeeInOrder(['Ben Marilyn', 'Ben Corbett', 'Ben Munro']);
    }

    public function test_search_finds_hills_by_the_name_of_their_mwis_area(): void
    {
        $this->hill('Ben Macdui', ['mwis_area' => 'EH']);
        $this->hill('Ben Cleuch', ['mwis_area' => 'SH']);

        Livewire::test('pages::hills')
            ->set('search', 'Cairng')
            ->assertSee('Ben Macdui')
            ->assertDontSee('Ben Cleuch')
            ->set('search', 'Cai')
            ->assertDontSee('Ben Macdui');
    }

    public function test_the_hill_page_shows_the_murray_section(): void
    {
        Area::create(['code' => 'M22', 'short_name' => 'M22', 'name' => '22: Shetland Islands', 'type' => 'murray_section', 'country' => 'S']);
        $hill = $this->hill('Ronas Hill', ['dobih_section' => '22']);

        $this->get(route('hills.show', $hill))
            ->assertSee('22. Shetland Islands')
            ->assertDontSee('Murray sub-section');
    }

    public function test_the_hill_page_shows_the_mwis_area_as_a_link(): void
    {
        $hill = $this->hill('Ben Cleuch', ['mwis_area' => 'SH', 'country' => 'S']);

        $this->get(route('hills.show', $hill))
            ->assertSee('Southeastern Highlands')
            ->assertSee('https://www.mwis.org.uk/forecasts/scottish/southeastern-highlands', false);
    }

    public function test_a_hill_with_no_mwis_area_shows_none(): void
    {
        $hill = $this->hill('Irish Hill');

        $this->get(route('hills.show', $hill))->assertDontSee('MWIS area');
    }
}

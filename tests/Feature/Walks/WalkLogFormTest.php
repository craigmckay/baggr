<?php

namespace Tests\Feature\Walks;

use App\Models\Group;
use App\Models\Hill;
use App\Models\HillClass;
use App\Models\User;
use App\Models\Walk;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class WalkLogFormTest extends TestCase
{
    use RefreshDatabase;

    private User $me;

    protected function setUp(): void
    {
        parent::setUp();

        $this->me = User::factory()->create();
    }

    private function form(array $queryParams = [])
    {
        return Livewire::actingAs($this->me)->withQueryParams($queryParams)->test('pages::walk-log');
    }

    private function groupWithMe(string $role = 'admin'): Group
    {
        $group = Group::factory()->create();
        $group->users()->attach($this->me, ['role' => $role]);

        return $group;
    }

    private function onList(string $code, Hill $hill): void
    {
        $class = HillClass::firstOrCreate(['code' => $code], ['name' => $code, 'show_in_list' => true, 'sort_order' => 1]);
        $hill->classes()->attach($class);
    }

    public function test_guests_are_sent_to_log_in_and_unverified_people_to_verify(): void
    {
        $this->get(route('walks.log'))->assertRedirect(route('login'));

        $this->actingAs(User::factory()->unverified()->create())
            ->get(route('walks.log'))
            ->assertRedirect(route('verification.notice'));
    }

    public function test_a_solo_walk_is_saved_and_shown(): void
    {
        $hill = Hill::factory()->create(['name' => 'Ben Cleuch']);

        $this->form()
            ->set('pickerDay', 0)
            ->call('addHill', $hill->id)
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('walks.show', Walk::firstOrFail()));

        $this->assertDatabaseHas('ascents', ['user_id' => $this->me->id, 'hill_id' => $hill->id]);
    }

    public function test_a_hill_in_the_address_is_already_on_the_form(): void
    {
        $hill = Hill::factory()->create(['slug' => 'ben-cleuch']);

        $this->form(['hill' => 'ben-cleuch'])->assertSet('days.0.hills', [$hill->id]);
    }

    public function test_a_group_in_the_address_is_already_chosen_but_only_if_you_can_log_into_it(): void
    {
        $mine = $this->groupWithMe('member');
        $theirs = Group::factory()->create();
        $watching = $this->groupWithMe('follower');

        $this->form(['group' => $mine->id])->assertSet('groupId', $mine->id);
        $this->form(['group' => $theirs->id])->assertSet('groupId', null);
        $this->form(['group' => $watching->id])->assertSet('groupId', null);
    }

    public function test_only_groups_you_can_log_into_are_offered(): void
    {
        $admin = $this->groupWithMe('admin');
        $member = $this->groupWithMe('member');
        $this->groupWithMe('follower');
        $this->groupWithMe('visitor');

        $offered = $this->form()->get('groups')->pluck('id')->all();

        $this->assertEqualsCanonicalizing([$admin->id, $member->id], $offered);
    }

    public function test_choosing_a_group_lets_you_pick_the_people_and_who_did_which_hill(): void
    {
        $group = $this->groupWithMe();
        $friend = User::factory()->create();
        $group->users()->attach($friend, ['role' => 'member']);
        $hill = Hill::factory()->create();

        $form = $this->form()
            ->set('groupId', $group->id)
            ->call('addHill', $hill->id)
            ->call('togglePerson', $friend->id)
            ->assertSet('people', [$this->me->id, $friend->id]);

        $form->call('toggleTick', 0, $hill->id, $me = $this->me->id);
        $form->call('save')->assertHasNoErrors();

        $this->assertDatabaseMissing('ascents', ['user_id' => $me, 'hill_id' => $hill->id]);
        $this->assertDatabaseHas('ascents', ['user_id' => $friend->id, 'hill_id' => $hill->id]);
    }

    public function test_someone_added_to_the_walk_is_ticked_for_every_hill_and_removed_when_dropped(): void
    {
        $group = $this->groupWithMe();
        $friend = User::factory()->create();
        $group->users()->attach($friend, ['role' => 'member']);
        $hill = Hill::factory()->create();

        $form = $this->form()->set('groupId', $group->id)->call('addHill', $hill->id);

        $form->call('togglePerson', $friend->id)->assertSet("ticks.0.{$hill->id}", [$this->me->id, $friend->id]);
        $form->call('togglePerson', $friend->id)->assertSet("ticks.0.{$hill->id}", [$this->me->id]);
    }

    public function test_changing_group_starts_the_people_again(): void
    {
        $group = $this->groupWithMe();
        $friend = User::factory()->create();
        $group->users()->attach($friend, ['role' => 'member']);

        $this->form()
            ->set('groupId', $group->id)
            ->call('togglePerson', $friend->id)
            ->call('addGuest')
            ->set('groupId', null)
            ->assertSet('people', [$this->me->id])
            ->assertSet('guests', []);
    }

    public function test_someone_outside_the_group_cannot_be_added(): void
    {
        $group = $this->groupWithMe();
        $stranger = User::factory()->create();

        $this->form()->set('groupId', $group->id)->call('togglePerson', $stranger->id)->assertSet('people', [$this->me->id]);
    }

    public function test_a_group_walk_can_have_guests_but_a_solo_walk_cannot(): void
    {
        $group = $this->groupWithMe();

        $this->form()->set('guestName', 'Old Pal')->call('addGuest')->assertSet('guests', []);

        $this->form()
            ->set('groupId', $group->id)
            ->set('guestName', 'Old Pal')->call('addGuest')
            ->set('guestName', 'Old Pal')->call('addGuest')
            ->assertSet('guests', ['Old Pal'])
            ->call('removeGuest', 0)
            ->assertSet('guests', []);
    }

    public function test_days_can_be_added_removed_and_hills_moved_between_them(): void
    {
        [$a, $b] = Hill::factory()->count(2)->create();

        $form = $this->form()->call('addHill', $a->id)->call('addHill', $b->id)->call('addDay');

        $form->assertCount('days', 2)
            ->call('moveHill', 0, $a->id, 'down')
            ->assertSet('days.0.hills', [$b->id, $a->id])
            ->call('moveToDay', 0, $b->id, 1)
            ->assertSet('days.0.hills', [$a->id])
            ->assertSet('days.1.hills', [$b->id])
            ->call('removeHill', 0, $a->id)
            ->assertSet('days.0.hills', [])
            ->call('removeDay', 0)
            ->assertCount('days', 1)
            ->assertSet('days.0.hills', [$b->id])
            ->call('removeDay', 0)
            ->assertCount('days', 1);
    }

    public function test_a_trip_saves_each_day_with_its_own_hills(): void
    {
        [$a, $b] = Hill::factory()->count(2)->create();

        $this->form()
            ->call('addHill', $a->id)
            ->call('addDay')
            ->set('pickerDay', 1)
            ->call('addHill', $b->id)
            ->set('days.1.date', now()->subDay()->toDateString())
            ->set('days.0.date', now()->subDays(2)->toDateString())
            ->call('save')
            ->assertHasNoErrors();

        $walk = Walk::with('days.hills')->firstOrFail();
        $this->assertSame([$a->id], $walk->days[0]->hills->pluck('id')->all());
        $this->assertSame([$b->id], $walk->days[1]->hills->pluck('id')->all());
    }

    public function test_the_hill_picker_searches_the_chosen_list_and_leaves_out_what_is_already_added(): void
    {
        $munro = Hill::factory()->create(['name' => 'Ben Alder']);
        $corbett = Hill::factory()->create(['name' => 'Ben Alder Cottage Hill']);
        $this->onList('M', $munro);
        $this->onList('C', $corbett);

        $form = $this->form()->set('search', 'ben alder');
        $this->assertSame([$munro->id], $form->get('results')->pluck('id')->all());

        $form->set('list', 'C');
        $this->assertSame([$corbett->id], $form->get('results')->pluck('id')->all());

        $form->set('list', 'all');
        $this->assertCount(2, $form->get('results'));

        // Adding a hill clears the search box, so search again.
        $form->call('addHill', $munro->id)->assertSet('search', '')->set('search', 'ben alder');
        $this->assertSame([$corbett->id], $form->get('results')->pluck('id')->all());
    }

    public function test_an_empty_search_points_at_the_other_lists(): void
    {
        Hill::factory()->create(['name' => 'Dumyat']);

        $this->form()->set('search', 'dumyat')->assertSeeHtml('data-test="no-results"');
        $this->form()->set('search', 'dumyat')->set('list', 'all')->assertDontSeeHtml('data-test="no-results"')->assertSee('Dumyat');
    }

    public function test_the_picker_waits_for_two_letters(): void
    {
        Hill::factory()->create(['name' => 'Ben Alder']);

        $this->assertCount(0, $this->form()->set('search', 'b')->get('results'));
    }

    public function test_a_walk_in_the_future_or_with_no_hills_is_refused(): void
    {
        $hill = Hill::factory()->create();

        $this->form()->call('save')->assertHasErrors(['days.0.hills']);

        $this->form()
            ->call('addHill', $hill->id)
            ->set('days.0.date', now()->addDay()->toDateString())
            ->call('save')
            ->assertHasErrors(['days.0.date']);

        $this->assertDatabaseCount('walks', 0);
    }

    public function test_a_walk_needs_a_sensible_time_taken(): void
    {
        $hill = Hill::factory()->create();

        $this->form()->call('addHill', $hill->id)->set('days.0.duration', '-5')->call('save')->assertHasErrors(['days.0.duration']);
        $this->form()->call('addHill', $hill->id)->set('days.0.duration', 'lots')->call('save')->assertHasErrors(['days.0.duration']);
    }

    public function test_direction_and_time_are_saved(): void
    {
        $hill = Hill::factory()->create();

        $this->form()
            ->call('addHill', $hill->id)
            ->set('days.0.direction', 'anticlockwise')
            ->set('days.0.duration', '240')
            ->set('title', 'Quick one')
            ->call('save')
            ->assertHasNoErrors();

        $walk = Walk::with('days')->firstOrFail();
        $this->assertSame('Quick one', $walk->title);
        $this->assertSame('anticlockwise', $walk->days[0]->direction->value);
        $this->assertSame(240, $walk->days[0]->duration_minutes);
    }
}

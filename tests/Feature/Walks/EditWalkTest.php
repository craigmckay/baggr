<?php

namespace Tests\Feature\Walks;

use App\Actions\Walks\LogWalk;
use App\Actions\Walks\UpdateWalk;
use App\Models\Ascent;
use App\Models\Group;
use App\Models\Hill;
use App\Models\User;
use App\Models\Walk;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class EditWalkTest extends TestCase
{
    use RefreshDatabase;

    private User $me;

    private User $sam;

    private Group $group;

    private Hill $a;

    private Hill $b;

    private Hill $c;

    protected function setUp(): void
    {
        parent::setUp();

        $this->me = User::factory()->create(['name' => 'Alex']);
        $this->sam = User::factory()->create(['name' => 'Sam']);
        $this->group = Group::factory()->create(['name' => 'Fife crew']);
        $this->group->users()->attach($this->me, ['role' => 'admin']);
        $this->group->users()->attach($this->sam, ['role' => 'member']);
        $this->a = Hill::factory()->create(['name' => 'Ben A']);
        $this->b = Hill::factory()->create(['name' => 'Ben B']);
        $this->c = Hill::factory()->create(['name' => 'Ben C']);
    }

    /**
     * @param  list<list<int>>  $days  the hill ids of each day
     * @param  array<string, mixed>  $extra
     */
    private function walk(array $days, array $extra = [], ?User $by = null): Walk
    {
        $by ??= $this->me;

        return app(LogWalk::class)->handle($by, [
            'group_id' => $this->group->id,
            'people' => [$this->me->id, $this->sam->id],
            'days' => array_map(fn (array $hills, int $i) => ['date' => '2026-05-0'.($i + 1), 'hills' => $hills], $days, array_keys($days)),
            ...$extra,
        ]);
    }

    private function form(Walk $walk, ?User $as = null)
    {
        return Livewire::actingAs($as ?? $this->me)->test('pages::walk-log', ['walk' => $walk]);
    }

    /**
     * @return list<string> "day:hill:person" for every ascent, sorted
     */
    private function ascents(Walk $walk): array
    {
        return Ascent::with(['walkDay', 'hill', 'user'])->get()
            ->filter(fn (Ascent $ascent) => $ascent->walkDay->walk_id === $walk->id)
            ->map(fn (Ascent $ascent) => $ascent->walkDay->day_number.':'.$ascent->hill->name.':'.$ascent->user->name)
            ->sort()->values()->all();
    }

    public function test_the_form_is_filled_in_with_the_walk(): void
    {
        $walk = $this->walk([[$this->b->id, $this->a->id]], ['title' => 'Round', 'notes' => 'Windy', 'guests' => ['Jo']]);

        $this->form($walk)
            ->assertSet('title', 'Round')
            ->assertSet('notes', 'Windy')
            ->assertSet('groupId', $this->group->id)
            ->assertSet('guests', ['Jo'])
            ->assertSet('days.0.date', '2026-05-01')
            ->assertSet('days.0.hills', [$this->b->id, $this->a->id])
            ->assertSee('Save changes')
            ->assertDontSee('Who with');

        $this->actingAs($this->me)->get(route('walks.edit', $walk))->assertOk()->assertSee('Edit walk');
        $this->actingAs($this->me)->get(route('walks.log'))->assertOk()->assertSee('Who with');
    }

    public function test_saving_without_changes_changes_nothing(): void
    {
        $walk = $this->walk([[$this->a->id, $this->b->id], [$this->c->id]], ['ticks' => [0 => [$this->b->id => [$this->sam->id]]]]);
        Ascent::query()->where('hill_id', $this->a->id)->where('user_id', $this->me->id)->update(['notes' => 'First one']);
        $before = Ascent::orderBy('id')->get(['id', 'user_id', 'hill_id', 'walk_day_id', 'notes'])->toArray();

        $this->form($walk)->call('save')->assertHasNoErrors()->assertRedirect(route('walks.show', $walk));

        $this->assertSame($before, Ascent::orderBy('id')->get(['id', 'user_id', 'hill_id', 'walk_day_id', 'notes'])->toArray());
        $this->assertSame(1, Walk::count());
        $this->assertSame(2, $walk->attendees()->count());
    }

    public function test_the_details_of_a_walk_can_be_corrected(): void
    {
        $walk = $this->walk([[$this->a->id, $this->b->id]]);

        $this->form($walk)
            ->set('title', 'Better name')
            ->set('notes', 'Snow')
            ->set('days.0.date', '2026-04-20')
            ->set('days.0.direction', 'clockwise')
            ->set('days.0.duration', '300')
            ->call('save')
            ->assertHasNoErrors();

        $walk->refresh();
        $day = $walk->days()->first();
        $this->assertSame('Better name', $walk->title);
        $this->assertSame('Snow', $walk->notes);
        $this->assertSame('2026-04-20', $day->date->toDateString());
        $this->assertSame('clockwise', $day->direction->value);
        $this->assertSame(300, $day->duration_minutes);
    }

    public function test_hills_can_be_dragged_into_a_new_order(): void
    {
        $walk = $this->walk([[$this->a->id, $this->b->id, $this->c->id]]);

        $this->form($walk)
            ->assertSeeHtml('wire:sort="sortHill"')
            ->call('sortHill', $this->c->id, 0, 0)
            ->assertSet('days.0.hills', [$this->c->id, $this->a->id, $this->b->id])
            ->call('sortHill', $this->c->id, 2, 0)
            ->assertSet('days.0.hills', [$this->a->id, $this->b->id, $this->c->id])
            ->call('sortHill', $this->b->id, 0, 0)
            ->call('sortHill', 999, 0, 0)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame([$this->b->id, $this->a->id, $this->c->id], $walk->days()->first()->hills->pluck('id')->all());
    }

    public function test_hills_can_be_added_and_taken_off(): void
    {
        $walk = $this->walk([[$this->a->id, $this->b->id]]);

        $this->form($walk)
            ->call('removeHill', 0, $this->a->id)
            ->call('addHill', $this->c->id)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(['1:Ben B:Alex', '1:Ben B:Sam', '1:Ben C:Alex', '1:Ben C:Sam'], $this->ascents($walk));
    }

    public function test_who_did_which_hill_can_be_changed(): void
    {
        $walk = $this->walk([[$this->a->id, $this->b->id]]);

        $this->form($walk)
            ->call('toggleTick', 0, $this->b->id, $this->sam->id)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(['1:Ben A:Alex', '1:Ben A:Sam', '1:Ben B:Alex'], $this->ascents($walk));
    }

    public function test_adding_a_person_leaves_everyone_elses_ticks_alone(): void
    {
        $ethan = User::factory()->create(['name' => 'Ethan']);
        $this->group->users()->attach($ethan, ['role' => 'member']);
        $walk = $this->walk([[$this->a->id, $this->b->id]], ['ticks' => [0 => [$this->b->id => [$this->me->id]]]]);

        $this->form($walk)->call('togglePerson', $ethan->id)->call('save')->assertHasNoErrors();

        $this->assertSame(['1:Ben A:Alex', '1:Ben A:Ethan', '1:Ben A:Sam', '1:Ben B:Alex', '1:Ben B:Ethan'], $this->ascents($walk));
        $this->assertSame(3, $walk->attendees()->count());
    }

    public function test_taking_a_person_off_removes_only_their_ascents(): void
    {
        $walk = $this->walk([[$this->a->id]]);

        $this->form($walk)->call('togglePerson', $this->sam->id)->call('save')->assertHasNoErrors();

        $this->assertSame(['1:Ben A:Alex'], $this->ascents($walk));
        $this->assertSame([$this->me->id], $walk->attendees()->pluck('user_id')->all());
    }

    public function test_a_note_stays_with_a_hill_moved_to_another_day(): void
    {
        $walk = $this->walk([[$this->a->id, $this->b->id], [$this->c->id]]);
        Ascent::query()->where('hill_id', $this->b->id)->where('user_id', $this->sam->id)->update(['notes' => 'Kept']);

        $this->form($walk)->call('moveToDay', 0, $this->b->id, 1)->call('save')->assertHasNoErrors();

        $moved = Ascent::query()->where('hill_id', $this->b->id)->where('user_id', $this->sam->id)->sole();
        $this->assertSame('Kept', $moved->notes);
        $this->assertSame(2, $moved->walkDay->day_number);
    }

    public function test_days_can_be_added_and_removed(): void
    {
        $walk = $this->walk([[$this->a->id], [$this->b->id]]);
        $secondDay = $walk->days()->where('day_number', 2)->value('id');

        $this->form($walk)
            ->call('removeDay', 0)
            ->call('addDay')
            ->set('pickerDay', 1)
            ->call('addHill', $this->c->id)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(['1:Ben B:Alex', '1:Ben B:Sam', '2:Ben C:Alex', '2:Ben C:Sam'], $this->ascents($walk));
        $this->assertSame(2, $walk->days()->count());
        $this->assertSame($secondDay, $walk->days()->where('day_number', 1)->value('id'), 'the day that stayed is the same day');
    }

    public function test_removing_a_day_keeps_each_days_ticks_with_it(): void
    {
        Livewire::actingAs($this->me)->test('pages::walk-log')
            ->set('groupId', $this->group->id)
            ->call('togglePerson', $this->sam->id)
            ->call('addDay')
            ->call('addDay')
            ->set('pickerDay', 2)
            ->call('addHill', $this->c->id)
            ->call('toggleTick', 2, $this->c->id, $this->sam->id)
            ->set('pickerDay', 0)
            ->call('addHill', $this->a->id)
            ->call('removeDay', 1)
            ->assertSet('ticks.0.'.$this->a->id, [$this->me->id, $this->sam->id])
            ->assertSet('ticks.1.'.$this->c->id, [$this->me->id]);
    }

    public function test_the_group_of_a_walk_cannot_be_changed(): void
    {
        $walk = $this->walk([[$this->a->id]]);
        $other = Group::factory()->create();
        $other->users()->attach($this->me, ['role' => 'admin']);

        $this->form($walk)
            ->set('groupId', $other->id)
            ->assertSet('groupId', $this->group->id)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame($this->group->id, $walk->refresh()->group_id);
    }

    public function test_guests_can_be_changed(): void
    {
        $walk = $this->walk([[$this->a->id]], ['guests' => ['Jo']]);

        $this->form($walk)
            ->call('removeGuest', 0)
            ->set('guestName', 'Max')
            ->call('addGuest')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(['Max'], $walk->attendees()->whereNotNull('guest_id')->get()->map->displayName()->all());
    }

    public function test_someone_who_has_since_left_the_group_stays_on_the_walk(): void
    {
        $walk = $this->walk([[$this->a->id]]);
        $this->group->users()->detach($this->sam);

        $this->form($walk)->assertSee('Sam')->call('save')->assertHasNoErrors();

        $this->assertSame(['1:Ben A:Alex', '1:Ben A:Sam'], $this->ascents($walk));
    }

    public function test_a_former_member_and_a_hill_left_with_nobody_survive_an_edit(): void
    {
        $walk = $this->walk([[$this->a->id, $this->b->id]], ['ticks' => [0 => [$this->b->id => [$this->sam->id]]]]);
        $this->sam->delete();

        $this->form($walk)->set('title', 'Still fine')->call('save')->assertHasNoErrors();

        $this->assertSame(['1:Ben A:Alex'], $this->ascents($walk));
        $this->assertSame(2, $walk->days()->first()->hills()->count());
        $this->assertSame(1, $walk->attendees()->whereNull('user_id')->whereNull('guest_id')->count());
    }

    public function test_a_hill_cannot_be_newly_left_with_nobody(): void
    {
        $walk = $this->walk([[$this->a->id]]);

        $this->form($walk)
            ->call('toggleTick', 0, $this->a->id, $this->me->id)
            ->call('toggleTick', 0, $this->a->id, $this->sam->id)
            ->call('save')
            ->assertHasErrors('days');

        $this->assertCount(2, $this->ascents($walk));
    }

    public function test_the_rules_for_logging_also_apply_to_editing(): void
    {
        $walk = $this->walk([[$this->a->id]]);

        $this->form($walk)->set('days.0.date', now()->addDay()->toDateString())->call('save')->assertHasErrors('days.0.date');
        $this->form($walk)->call('removeHill', 0, $this->a->id)->call('save')->assertHasErrors('days.0.hills');
    }

    public function test_a_day_belonging_to_another_walk_is_never_touched(): void
    {
        $walk = $this->walk([[$this->a->id]]);
        $other = $this->walk([[$this->b->id]]);
        $otherDay = $other->days()->value('id');

        $this->form($walk)->set('days.0.id', $otherDay)->call('save')->assertHasNoErrors();

        $this->assertSame(['1:Ben B:Alex', '1:Ben B:Sam'], $this->ascents($other));
        $this->assertSame(['1:Ben A:Alex', '1:Ben A:Sam'], $this->ascents($walk));
        $this->assertSame(1, $walk->days()->count());
    }

    public function test_who_can_edit_a_group_walk(): void
    {
        $member = User::factory()->create();
        $admin = User::factory()->create();
        $this->group->users()->attach($member, ['role' => 'member']);
        $this->group->users()->attach($admin, ['role' => 'admin']);
        $walk = $this->walk([[$this->a->id]], by: $this->sam);

        $this->assertTrue($walk->canBeEditedBy($this->sam), 'who logged it');
        $this->assertTrue($walk->canBeEditedBy($this->me), 'on the walk');
        $this->assertTrue($walk->canBeEditedBy($admin), 'an admin who was not there');
        $this->assertFalse($walk->canBeEditedBy($member), 'a member who was not there');
        $this->assertFalse($walk->canBeEditedBy(User::factory()->create()), 'a stranger');

        $this->group->users()->updateExistingPivot($this->sam->id, ['role' => 'follower']);
        $this->assertFalse($walk->canBeEditedBy($this->sam), 'now a follower');

        $this->group->users()->detach($this->sam);
        $this->assertFalse($walk->canBeEditedBy($this->sam), 'has left');
    }

    public function test_a_solo_walk_is_only_its_owners_to_edit(): void
    {
        $walk = app(LogWalk::class)->handle($this->sam, ['days' => [['date' => '2026-05-01', 'hills' => [$this->a->id]]]]);

        $this->assertTrue($walk->canBeEditedBy($this->sam));
        $this->assertFalse($walk->canBeEditedBy($this->me), 'being the admin of one of their groups gives nothing');

        $this->form($walk, $this->sam)->call('addHill', $this->b->id)->call('save')->assertHasNoErrors();

        $this->assertSame(['1:Ben A:Sam', '1:Ben B:Sam'], $this->ascents($walk));
    }

    public function test_the_edit_page_and_button_are_closed_to_others(): void
    {
        $member = User::factory()->create();
        $this->group->users()->attach($member, ['role' => 'member']);
        $walk = $this->walk([[$this->a->id]]);

        $this->get(route('walks.edit', $walk))->assertRedirect(route('login'));
        $this->actingAs($member)->get(route('walks.edit', $walk))->assertForbidden();
        $this->actingAs(User::factory()->create())->get(route('walks.edit', $walk))->assertNotFound();
        $this->actingAs($member)->get(route('walks.show', $walk))->assertOk()->assertDontSee(route('walks.edit', $walk), false);
        $this->actingAs($this->sam)->get(route('walks.show', $walk))->assertSee(route('walks.edit', $walk), false);

        $this->expectException(AuthorizationException::class);
        app(UpdateWalk::class)->handle($member, $walk, ['days' => [['date' => '2026-05-01', 'hills' => [$this->a->id]]]]);
    }

    public function test_losing_the_right_to_edit_while_the_form_is_open_stops_the_save(): void
    {
        $walk = $this->walk([[$this->a->id]]);
        $form = $this->form($walk, $this->sam)->set('title', 'Too late');
        $this->group->users()->detach($this->sam);

        $form->call('save')->assertForbidden();

        $this->assertNull($walk->refresh()->title);
    }
}

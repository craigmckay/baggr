<?php

namespace Tests\Feature\Walks;

use App\Actions\Walks\LogWalk;
use App\Enums\WalkStatus;
use App\Models\Ascent;
use App\Models\Group;
use App\Models\Guest;
use App\Models\Hill;
use App\Models\User;
use App\Models\Walk;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class LogWalkTest extends TestCase
{
    use RefreshDatabase;

    private User $me;

    protected function setUp(): void
    {
        parent::setUp();

        $this->me = User::factory()->create();
    }

    /** @return array<string, mixed> */
    private function day(array $hills, string $date = '2026-11-01', array $extra = []): array
    {
        return ['date' => $date, 'direction' => null, 'duration_minutes' => null, 'hills' => $hills, ...$extra];
    }

    private function groupWith(User ...$people): Group
    {
        $group = Group::factory()->create();

        foreach ($people as $index => $person) {
            $group->users()->attach($person, ['role' => $index === 0 ? 'admin' : 'member']);
        }

        return $group;
    }

    private function log(array $data, ?User $by = null): Walk
    {
        return app(LogWalk::class)->handle($by ?? $this->me, $data);
    }

    public function test_a_quick_solo_log_records_a_completed_walk_with_one_ascent(): void
    {
        $hill = Hill::factory()->create();

        $walk = $this->log(['days' => [$this->day([$hill->id], now()->toDateString())]]);

        $this->assertSame(WalkStatus::Completed, $walk->status);
        $this->assertNull($walk->group_id);
        $this->assertSame($this->me->id, $walk->organiser_id);
        $this->assertCount(1, $walk->days);
        $this->assertSame([$hill->id], $walk->days->first()->hills->pluck('id')->all());
        $this->assertDatabaseHas('ascents', ['user_id' => $this->me->id, 'hill_id' => $hill->id, 'walk_day_id' => $walk->days->first()->id]);
        $this->assertDatabaseHas('walk_attendees', ['walk_id' => $walk->id, 'user_id' => $this->me->id, 'attended' => true]);
    }

    public function test_a_round_keeps_its_hills_in_order_with_direction_and_time(): void
    {
        [$a, $b, $c] = Hill::factory()->count(3)->create();

        $walk = $this->log([
            'title' => '  Glen Clova round  ',
            'notes' => 'Clear all day.',
            'days' => [$this->day([$c->id, $a->id, $b->id], '2026-11-01', ['direction' => 'clockwise', 'duration_minutes' => 390])],
        ]);

        $day = $walk->days->first();
        $this->assertSame('Glen Clova round', $walk->title);
        $this->assertSame([$c->id, $a->id, $b->id], $day->hills->pluck('id')->all());
        $this->assertSame([1, 2, 3], $day->hills->pluck('pivot.position')->all());
        $this->assertSame('clockwise', $day->direction->value);
        $this->assertSame(390, $day->duration_minutes);
        $this->assertSame('Clear all day.', $walk->notes);
    }

    public function test_a_trip_is_several_days_each_with_its_own_hills(): void
    {
        [$a, $b, $c] = Hill::factory()->count(3)->create();

        $walk = $this->log(['days' => [
            $this->day([$a->id], '2026-10-10'),
            $this->day([$b->id, $c->id], '2026-10-11'),
        ]]);

        $this->assertSame([1, 2], $walk->days->pluck('day_number')->all());
        $this->assertSame(3, Ascent::where('user_id', $this->me->id)->count());
        $this->assertSame([$b->id, $c->id], $walk->days->last()->hills->pluck('id')->all());
    }

    public function test_a_group_walk_records_who_did_which_hill_and_who_came_along(): void
    {
        $friend = User::factory()->create();
        $group = $this->groupWith($this->me, $friend);
        [$a, $b] = Hill::factory()->count(2)->create();

        $walk = $this->log([
            'group_id' => $group->id,
            'people' => [$this->me->id, $friend->id],
            'ticks' => [0 => [$a->id => [$this->me->id, $friend->id], $b->id => [$friend->id]]],
            'guests' => ['Old Pal', '  ', 'Old Pal'],
            'days' => [$this->day([$a->id, $b->id])],
        ]);

        $this->assertSame($group->id, $walk->group_id);
        $this->assertDatabaseHas('ascents', ['user_id' => $this->me->id, 'hill_id' => $a->id]);
        $this->assertDatabaseMissing('ascents', ['user_id' => $this->me->id, 'hill_id' => $b->id]);
        $this->assertDatabaseHas('ascents', ['user_id' => $friend->id, 'hill_id' => $b->id]);

        $this->assertSame(1, Guest::where('group_id', $group->id)->count());
        $this->assertSame(3, $walk->attendees()->count());
        $this->assertSame(0, Ascent::whereNotIn('user_id', [$this->me->id, $friend->id])->count());
    }

    public function test_everyone_is_ticked_for_every_hill_unless_told_otherwise(): void
    {
        $friend = User::factory()->create();
        $group = $this->groupWith($this->me, $friend);
        $hill = Hill::factory()->create();

        $this->log(['group_id' => $group->id, 'people' => [$this->me->id, $friend->id], 'days' => [$this->day([$hill->id])]]);

        $this->assertSame(2, Ascent::where('hill_id', $hill->id)->count());
    }

    public function test_repeat_ascents_are_all_kept(): void
    {
        $hill = Hill::factory()->create();

        $this->log(['days' => [$this->day([$hill->id], '2020-05-01')]]);
        $this->log(['days' => [$this->day([$hill->id], '2026-05-01')]]);

        $this->assertSame(2, Ascent::where('user_id', $this->me->id)->where('hill_id', $hill->id)->count());
    }

    public function test_a_guest_never_gets_ascents_and_a_solo_walk_has_no_guests(): void
    {
        $hill = Hill::factory()->create();

        $this->log(['guests' => ['Someone'], 'days' => [$this->day([$hill->id])]]);

        $this->assertDatabaseCount('guests', 0);
        $this->assertSame(1, Ascent::count());
    }

    public function test_only_admins_and_members_can_log_into_a_group(): void
    {
        $group = $this->groupWith(User::factory()->create());
        $hill = Hill::factory()->create();

        foreach (['follower', 'visitor', null] as $role) {
            $person = User::factory()->create();
            if ($role !== null) {
                $group->users()->attach($person, ['role' => $role]);
            }

            try {
                $this->log(['group_id' => $group->id, 'people' => [$person->id], 'days' => [$this->day([$hill->id])]], $person);
                $this->fail('A '.($role ?? 'stranger').' was allowed to log into the group.');
            } catch (AuthorizationException) {
                $this->assertDatabaseCount('walks', 0);
            }
        }
    }

    public function test_the_people_must_be_admins_or_members_of_the_group(): void
    {
        $group = $this->groupWith($this->me);
        $follower = User::factory()->create();
        $stranger = User::factory()->create();
        $group->users()->attach($follower, ['role' => 'follower']);
        $hill = Hill::factory()->create();

        foreach ([[$stranger->id], [$follower->id], [$this->me->id, $stranger->id], []] as $people) {
            try {
                $this->log(['group_id' => $group->id, 'people' => $people, 'days' => [$this->day([$hill->id])]]);
                $this->fail('Someone who is not an admin or member was allowed on the walk.');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('people', $e->errors());
            }
        }

        $this->assertDatabaseCount('walks', 0);
        $this->assertDatabaseCount('ascents', 0);
    }

    public function test_a_solo_walk_is_only_ever_the_logger(): void
    {
        $other = User::factory()->create();
        $hill = Hill::factory()->create();

        $this->log(['people' => [$other->id], 'days' => [$this->day([$hill->id])]]);

        $this->assertDatabaseMissing('ascents', ['user_id' => $other->id]);
        $this->assertDatabaseHas('ascents', ['user_id' => $this->me->id]);
    }

    public function test_walks_that_make_no_sense_are_refused_and_leave_nothing_behind(): void
    {
        $friend = User::factory()->create();
        $group = $this->groupWith($this->me, $friend);
        [$a, $b] = Hill::factory()->count(2)->create();
        $tomorrow = now()->addDay()->toDateString();

        $bad = [
            'no days' => ['days' => []],
            'a day with no hills' => ['days' => [$this->day([])]],
            'a hill twice in a day' => ['days' => [$this->day([$a->id, $a->id])]],
            'a hill that does not exist' => ['days' => [$this->day([999999])]],
            'a date in the future' => ['days' => [$this->day([$a->id], $tomorrow)]],
            'no date' => ['days' => [$this->day([$a->id], '')]],
            'an unknown direction' => ['days' => [$this->day([$a->id], '2026-11-01', ['direction' => 'sideways'])]],
            'a second day that fails' => ['days' => [$this->day([$a->id]), $this->day([$b->id], $tomorrow)]],
        ];

        foreach ($bad as $why => $data) {
            try {
                $this->log($data);
                $this->fail("Accepted {$why}.");
            } catch (ValidationException) {
                $this->assertDatabaseCount('walks', 0);
                $this->assertDatabaseCount('ascents', 0);
            }
        }

        $ticks = [
            'a hill nobody climbed' => [0 => [$a->id => []]],
            'someone not on the walk' => [0 => [$a->id => [$friend->id + 99]]],
        ];

        foreach ($ticks as $why => $tick) {
            try {
                $this->log(['group_id' => $group->id, 'people' => [$this->me->id], 'ticks' => $tick, 'days' => [$this->day([$a->id])]]);
                $this->fail("Accepted {$why}.");
            } catch (ValidationException) {
                $this->assertDatabaseCount('walks', 0);
            }
        }
    }
}

<?php

namespace Tests\Feature\Walks;

use App\Actions\Walks\LogWalk;
use App\Models\Group;
use App\Models\Hill;
use App\Models\User;
use App\Models\Walk;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class WalkPagesTest extends TestCase
{
    use RefreshDatabase;

    /** @param  list<int>  $hills */
    private function logWalk(User $by, array $hills, ?Group $group = null, array $people = [], string $date = '2026-11-01', array $extra = []): Walk
    {
        return app(LogWalk::class)->handle($by, [
            'group_id' => $group?->id,
            'people' => $people ?: [$by->id],
            'days' => [['date' => $date, 'direction' => null, 'duration_minutes' => null, 'hills' => $hills]],
            ...$extra,
        ]);
    }

    private function groupOf(User $admin, array $others = []): Group
    {
        $group = Group::factory()->create(['name' => 'Fife crew']);
        $group->users()->attach($admin, ['role' => 'admin']);

        foreach ($others as $user => $role) {
            $group->users()->attach($user, ['role' => $role]);
        }

        return $group;
    }

    public function test_the_walks_list_shows_my_walks_newest_first_and_not_other_peoples(): void
    {
        $me = User::factory()->create();
        $other = User::factory()->create();
        $old = Hill::factory()->create(['name' => 'Old Hill']);
        $new = Hill::factory()->create(['name' => 'New Hill']);
        $theirs = Hill::factory()->create(['name' => 'Their Hill']);

        $this->logWalk($me, [$old->id], date: '2025-01-01');
        $this->logWalk($me, [$new->id], date: '2026-06-01');
        $this->logWalk($other, [$theirs->id]);

        $this->actingAs($me)->get(route('walks.index'))
            ->assertOk()
            ->assertSeeInOrder(['New Hill', 'Old Hill'])
            ->assertDontSee('Their Hill');
    }

    public function test_a_walk_i_was_on_but_did_not_log_is_in_my_list(): void
    {
        $organiser = User::factory()->create();
        $me = User::factory()->create();
        $group = $this->groupOf($organiser, [$me->id => 'member']);
        $hill = Hill::factory()->create(['name' => 'Shared Hill']);

        $this->logWalk($organiser, [$hill->id], $group, [$organiser->id, $me->id]);

        $this->actingAs($me)->get(route('walks.index'))->assertSee('Shared Hill');
    }

    public function test_the_walk_page_shows_the_hills_in_order_with_who_did_each(): void
    {
        $me = User::factory()->create(['name' => 'Craig']);
        $friend = User::factory()->create(['name' => 'Alistair']);
        $group = $this->groupOf($me, [$friend->id => 'member']);
        [$a, $b] = [Hill::factory()->create(['name' => 'Second Hill']), Hill::factory()->create(['name' => 'First Hill'])];

        $walk = $this->logWalk($me, [$b->id, $a->id], $group, [$me->id, $friend->id], extra: [
            'title' => 'Fife round',
            'notes' => 'Windy on top.',
            'guests' => ['Old Pal'],
            'ticks' => [0 => [$b->id => [$me->id, $friend->id], $a->id => [$friend->id]]],
        ]);

        $this->actingAs($me)->get(route('walks.show', $walk))
            ->assertOk()
            ->assertSeeInOrder(['Fife round', 'First Hill', 'Alistair, Craig', 'Second Hill', 'Alistair'])
            ->assertSee('Windy on top.')
            ->assertSee('Old Pal')
            ->assertSee('Fife crew')
            ->assertSee('Logged by Craig');
    }

    public function test_who_can_see_a_walk(): void
    {
        $organiser = User::factory()->create();
        $member = User::factory()->create();
        $follower = User::factory()->create();
        $visitor = User::factory()->create();
        $stranger = User::factory()->create();
        $group = $this->groupOf($organiser, [$member->id => 'member', $follower->id => 'follower', $visitor->id => 'visitor']);
        $walk = $this->logWalk($organiser, [Hill::factory()->create()->id], $group);

        $this->actingAs($organiser)->get(route('walks.show', $walk))->assertOk();
        $this->actingAs($member)->get(route('walks.show', $walk))->assertOk();
        $this->actingAs($follower)->get(route('walks.show', $walk))->assertOk();
        $this->actingAs($visitor)->get(route('walks.show', $walk))->assertNotFound();
        $this->actingAs($stranger)->get(route('walks.show', $walk))->assertNotFound();
        auth()->logout();
        $this->get(route('walks.show', $walk))->assertRedirect(route('login'));
    }

    public function test_a_solo_walk_is_private_to_the_person(): void
    {
        $me = User::factory()->create();
        $walk = $this->logWalk($me, [Hill::factory()->create()->id]);

        $this->actingAs(User::factory()->create())->get(route('walks.show', $walk))->assertNotFound();
    }

    public function test_the_organiser_can_delete_a_walk_and_its_ascents_go_with_it(): void
    {
        $me = User::factory()->create();
        $walk = $this->logWalk($me, [Hill::factory()->create()->id]);

        Livewire::actingAs($me)->test('pages::walk-show', ['walk' => $walk])
            ->call('delete')
            ->assertRedirect(route('walks.index'));

        $this->assertModelMissing($walk);
        $this->assertDatabaseCount('ascents', 0);
        $this->assertDatabaseCount('walk_days', 0);
    }

    public function test_a_group_admin_can_delete_a_walk_but_a_plain_member_cannot(): void
    {
        $admin = User::factory()->create();
        $organiser = User::factory()->create();
        $other = User::factory()->create();
        $group = $this->groupOf($admin, [$organiser->id => 'member', $other->id => 'member']);
        $walk = $this->logWalk($organiser, [Hill::factory()->create()->id], $group, [$organiser->id, $other->id]);

        Livewire::actingAs($other)->test('pages::walk-show', ['walk' => $walk])->call('delete')->assertForbidden();
        $this->assertModelExists($walk);

        Livewire::actingAs($admin)->test('pages::walk-show', ['walk' => $walk])->call('delete')->assertRedirect(route('walks.index'));
        $this->assertModelMissing($walk);
    }

    public function test_deleting_an_account_removes_their_ascents_and_the_walk_shows_a_former_member(): void
    {
        $organiser = User::factory()->create();
        $leaver = User::factory()->create(['name' => 'Gone Person']);
        $group = $this->groupOf($organiser, [$leaver->id => 'member']);
        $hill = Hill::factory()->create();
        $walk = $this->logWalk($organiser, [$hill->id], $group, [$organiser->id, $leaver->id]);

        $leaver->delete();

        $this->assertDatabaseMissing('ascents', ['user_id' => $leaver->id]);
        $this->assertDatabaseHas('ascents', ['user_id' => $organiser->id, 'hill_id' => $hill->id]);

        $this->actingAs($organiser)->get(route('walks.show', $walk))
            ->assertOk()
            ->assertSee('Former member')
            ->assertDontSee('Gone Person');
    }

    public function test_a_deleted_organiser_leaves_the_walk_in_place(): void
    {
        $organiser = User::factory()->create();
        $friend = User::factory()->create();
        $group = $this->groupOf($friend, [$organiser->id => 'member']);
        $walk = $this->logWalk($organiser, [Hill::factory()->create()->id], $group, [$organiser->id, $friend->id]);

        $organiser->delete();

        $this->actingAs($friend)->get(route('walks.show', $walk))->assertOk()->assertSee('Logged by a former member');
    }

    public function test_the_group_page_lists_its_walks_and_offers_to_log_one(): void
    {
        $admin = User::factory()->create();
        $follower = User::factory()->create();
        $group = $this->groupOf($admin, [$follower->id => 'follower']);
        $this->logWalk($admin, [Hill::factory()->create(['name' => 'Group Hill'])->id], $group);

        $this->actingAs($admin)->get(route('groups.show', $group))
            ->assertSee('Group Hill')
            ->assertSee('Log a walk');

        $this->actingAs($follower)->get(route('groups.show', $group))
            ->assertSee('Group Hill')
            ->assertDontSee('Log a walk');
    }

    public function test_the_hill_page_shows_my_ascents_and_a_log_button_only_to_someone_who_can_use_them(): void
    {
        $me = User::factory()->create();
        $hill = Hill::factory()->create(['slug' => 'ben-cleuch']);

        $this->get(route('hills.show', $hill))->assertOk()->assertDontSee('Log this hill');
        $this->actingAs(User::factory()->unverified()->create())->get(route('hills.show', $hill))->assertDontSee('Log this hill');

        $this->actingAs($me)->get(route('hills.show', $hill))
            ->assertSee('Log this hill')
            ->assertSee("haven't logged this hill yet")
            ->assertSee(route('walks.log', ['hill' => 'ben-cleuch']), false);

        $this->logWalk($me, [$hill->id], date: '2020-05-01');
        $this->logWalk($me, [$hill->id], date: '2026-05-01');

        $this->actingAs($me)->get(route('hills.show', $hill))
            ->assertSee('You have climbed this 2 times')
            ->assertSeeInOrder(['1 May 2020', '1 May 2026']);
    }

    public function test_the_hill_page_never_shows_someone_elses_ascents(): void
    {
        $other = User::factory()->create();
        $hill = Hill::factory()->create();
        $this->logWalk($other, [$hill->id], date: '2020-05-01');

        $this->actingAs(User::factory()->create())->get(route('hills.show', $hill))
            ->assertDontSee('1 May 2020')
            ->assertSee("haven't logged this hill yet");
    }
}

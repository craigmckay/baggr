<?php

namespace Tests\Feature\Groups;

use App\Enums\GroupRole;
use App\Models\Group;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class GroupTest extends TestCase
{
    use RefreshDatabase;

    /** A group with one person in it at the given role. */
    private function groupWith(User $user, GroupRole $role = GroupRole::Admin): Group
    {
        $group = Group::factory()->create();
        $group->users()->attach($user, ['role' => $role->value]);

        return $group;
    }

    public function test_guests_are_sent_to_log_in(): void
    {
        $this->get(route('groups.index'))->assertRedirect(route('login'));
    }

    public function test_an_unverified_person_must_verify_before_using_groups(): void
    {
        $this->actingAs(User::factory()->unverified()->create())
            ->get(route('groups.index'))
            ->assertRedirect(route('verification.notice'));
    }

    public function test_anyone_signed_in_can_start_a_group_and_becomes_its_admin(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)->test('pages::groups')
            ->set('name', 'The Sunday regulars')
            ->set('colour', Group::COLOURS['Heather'])
            ->call('create')
            ->assertHasNoErrors();

        $group = Group::firstOrFail();
        $this->assertSame('The Sunday regulars', $group->name);
        $this->assertSame(GroupRole::Admin, $group->roleOf($user));
    }

    public function test_a_group_needs_a_name_and_one_of_the_offered_colours(): void
    {
        Livewire::actingAs(User::factory()->create())->test('pages::groups')
            ->set('name', '')
            ->set('colour', '#123456')
            ->call('create')
            ->assertHasErrors(['name', 'colour']);

        $this->assertDatabaseCount('groups', 0);
    }

    public function test_people_see_their_groups_and_what_they_are_in_each(): void
    {
        $user = User::factory()->create();
        $this->groupWith($user, GroupRole::Follower)->update(['name' => 'Fife crew']);

        $this->actingAs($user)->get(route('groups.index'))
            ->assertOk()
            ->assertSee('Fife crew')
            ->assertSee('Follower');
    }

    public function test_someone_outside_a_group_cannot_tell_it_exists(): void
    {
        $group = $this->groupWith(User::factory()->create());

        $this->actingAs(User::factory()->create())
            ->get(route('groups.show', $group))
            ->assertNotFound();
    }

    public function test_a_visitor_sees_only_that_they_are_waiting(): void
    {
        $admin = User::factory()->create(['name' => 'Alistair Admin', 'email' => 'alistair@example.com']);
        $group = $this->groupWith($admin);
        $visitor = User::factory()->create();
        $group->users()->attach($visitor, ['role' => 'visitor']);

        $this->actingAs($visitor)->get(route('groups.show', $group))
            ->assertOk()
            ->assertSee('waiting for an admin to approve you')
            ->assertDontSee('Alistair Admin')
            ->assertDontSee('alistair@example.com')
            ->assertDontSee('Invite by email');
    }

    public function test_a_member_sees_the_people_but_no_admin_controls(): void
    {
        $admin = User::factory()->create(['name' => 'Alistair Admin', 'email' => 'alistair@example.com']);
        $group = $this->groupWith($admin);
        $member = User::factory()->create();
        $group->users()->attach($member, ['role' => 'member']);

        $this->actingAs($member)->get(route('groups.show', $group))
            ->assertOk()
            ->assertSee('Alistair Admin')
            ->assertDontSee('alistair@example.com')
            ->assertDontSee('Invite by email')
            ->assertDontSee('Group link');
    }

    public function test_an_admin_sees_the_controls(): void
    {
        $group = $this->groupWith($admin = User::factory()->create());

        $this->actingAs($admin)->get(route('groups.show', $group))
            ->assertOk()
            ->assertSee('Invite by email')
            ->assertSee('Group link');
    }

    public function test_an_admin_can_change_a_role(): void
    {
        $group = $this->groupWith($admin = User::factory()->create());
        $member = User::factory()->create();
        $group->users()->attach($member, ['role' => 'member']);

        Livewire::actingAs($admin)->test('pages::group-show', ['group' => $group])
            ->call('changeRole', $member->id, 'follower');

        $this->assertSame(GroupRole::Follower, $group->roleOf($member));
    }

    public function test_visitor_cannot_be_handed_out_from_the_front_end(): void
    {
        $group = $this->groupWith($admin = User::factory()->create());
        $member = User::factory()->create();
        $group->users()->attach($member, ['role' => 'member']);

        Livewire::actingAs($admin)->test('pages::group-show', ['group' => $group])
            ->call('changeRole', $member->id, 'visitor')
            ->assertStatus(422);

        $this->assertSame(GroupRole::Member, $group->roleOf($member));
    }

    public function test_only_admins_can_manage_people(): void
    {
        $group = $this->groupWith(User::factory()->create());
        $member = User::factory()->create();
        $other = User::factory()->create();
        $group->users()->attach($member, ['role' => 'member']);
        $group->users()->attach($other, ['role' => 'member']);

        // A refused call ends the component, so each attempt gets a fresh one.
        $attempt = fn () => Livewire::actingAs($member)->test('pages::group-show', ['group' => $group]);

        $attempt()->call('changeRole', $other->id, 'admin')->assertForbidden();
        $attempt()->call('remove', $other->id)->assertForbidden();
        $attempt()->call('makeLink')->assertForbidden();
        $attempt()->set('inviteEmail', 'new@example.com')->call('invite')->assertForbidden();

        $this->assertSame(GroupRole::Member, $group->roleOf($other));
    }

    public function test_a_follower_cannot_manage_anything(): void
    {
        $group = $this->groupWith(User::factory()->create());
        $follower = User::factory()->create();
        $other = User::factory()->create();
        $group->users()->attach($follower, ['role' => 'follower']);
        $group->users()->attach($other, ['role' => 'member']);

        Livewire::actingAs($follower)->test('pages::group-show', ['group' => $group])
            ->call('remove', $other->id)
            ->assertForbidden();
    }

    public function test_an_admin_can_demote_or_remove_another_admin(): void
    {
        $group = $this->groupWith($first = User::factory()->create());
        $second = User::factory()->create();
        $third = User::factory()->create();
        $group->users()->attach($second, ['role' => 'admin']);
        $group->users()->attach($third, ['role' => 'admin']);

        $component = Livewire::actingAs($first)->test('pages::group-show', ['group' => $group]);

        $component->call('changeRole', $second->id, 'member');
        $component->call('remove', $third->id);

        $this->assertSame(GroupRole::Member, $group->roleOf($second));
        $this->assertNull($group->roleOf($third));
    }

    public function test_the_last_admin_cannot_be_demoted_removed_or_leave(): void
    {
        $group = $this->groupWith($admin = User::factory()->create());

        $component = Livewire::actingAs($admin)->test('pages::group-show', ['group' => $group]);

        $component->call('changeRole', $admin->id, 'member');
        $this->assertSame(GroupRole::Admin, $group->roleOf($admin));

        $component->call('remove', $admin->id);
        $this->assertSame(GroupRole::Admin, $group->roleOf($admin));

        $component->call('leave')->assertNoRedirect();
        $this->assertSame(GroupRole::Admin, $group->roleOf($admin));
    }

    public function test_an_admin_can_leave_once_someone_else_is_admin(): void
    {
        $group = $this->groupWith($admin = User::factory()->create());
        $other = User::factory()->create();
        $group->users()->attach($other, ['role' => 'admin']);

        Livewire::actingAs($admin)->test('pages::group-show', ['group' => $group])
            ->call('leave')
            ->assertRedirect(route('groups.index'));

        $this->assertNull($group->roleOf($admin));
    }

    public function test_leaving_a_group_keeps_the_person_and_their_account(): void
    {
        $group = $this->groupWith(User::factory()->create());
        $member = User::factory()->create();
        $group->users()->attach($member, ['role' => 'member']);

        Livewire::actingAs($member)->test('pages::group-show', ['group' => $group])->call('leave');

        $this->assertNull($group->roleOf($member));
        $this->assertModelExists($member);
    }

    public function test_a_visitor_is_approved_into_the_role_an_admin_picks(): void
    {
        $group = $this->groupWith($admin = User::factory()->create());
        $visitor = User::factory()->create();
        $group->users()->attach($visitor, ['role' => 'visitor']);

        Livewire::actingAs($admin)->test('pages::group-show', ['group' => $group])
            ->set("visitorRole.{$visitor->id}", 'follower')
            ->call('approve', $visitor->id);

        $this->assertSame(GroupRole::Follower, $group->roleOf($visitor));
    }

    public function test_an_unpicked_visitor_is_approved_as_a_member(): void
    {
        $group = $this->groupWith($admin = User::factory()->create());
        $visitor = User::factory()->create();
        $group->users()->attach($visitor, ['role' => 'visitor']);

        Livewire::actingAs($admin)->test('pages::group-show', ['group' => $group])
            ->call('approve', $visitor->id);

        $this->assertSame(GroupRole::Member, $group->roleOf($visitor));
    }

    public function test_a_visitor_can_be_declined(): void
    {
        $group = $this->groupWith($admin = User::factory()->create());
        $visitor = User::factory()->create();
        $group->users()->attach($visitor, ['role' => 'visitor']);

        Livewire::actingAs($admin)->test('pages::group-show', ['group' => $group])
            ->call('remove', $visitor->id);

        $this->assertNull($group->roleOf($visitor));
    }

    public function test_an_admin_cannot_manage_someone_in_another_group(): void
    {
        $group = $this->groupWith($admin = User::factory()->create());
        $stranger = User::factory()->create();
        $this->groupWith($stranger);

        Livewire::actingAs($admin)->test('pages::group-show', ['group' => $group])
            ->call('remove', $stranger->id)
            ->assertNotFound();
    }

    public function test_deleting_an_account_leaves_the_group_and_its_other_people(): void
    {
        $group = $this->groupWith($admin = User::factory()->create());
        $member = User::factory()->create();
        $group->users()->attach($member, ['role' => 'member']);

        $member->delete();

        $this->assertNotNull(Group::find($group->id));
        $this->assertSame(GroupRole::Admin, $group->roleOf($admin));
        $this->assertDatabaseMissing('group_user', ['user_id' => $member->id]);
    }
}

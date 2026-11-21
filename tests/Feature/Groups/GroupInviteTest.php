<?php

namespace Tests\Feature\Groups;

use App\Enums\GroupRole;
use App\Mail\GroupInvitation;
use App\Models\Group;
use App\Models\GroupInvite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class GroupInviteTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Group $group;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['name' => 'Alistair Admin']);
        $this->group = Group::factory()->create(['name' => 'Fife crew']);
        $this->group->users()->attach($this->admin, ['role' => 'admin']);
    }

    private function inviteFor(string $email, GroupRole $role = GroupRole::Member): GroupInvite
    {
        return GroupInvite::issue($this->group, $email, $role, $this->admin);
    }

    /** @return array<string, string> */
    private function newAccount(): array
    {
        return [
            'name' => 'Nell Newcomer',
            'password' => 'a-long-password-1',
            'password_confirmation' => 'a-long-password-1',
        ];
    }

    public function test_an_admin_invites_by_email_and_chooses_the_role(): void
    {
        Mail::fake();

        Livewire::actingAs($this->admin)->test('pages::group-show', ['group' => $this->group])
            ->set('inviteEmail', 'Newcomer@Example.com')
            ->set('inviteRole', 'follower')
            ->call('invite')
            ->assertHasNoErrors();

        $invite = GroupInvite::firstOrFail();
        $this->assertSame('newcomer@example.com', $invite->email);
        $this->assertSame(GroupRole::Follower, $invite->role);
        $this->assertTrue($invite->expires_at->between(now()->addDays(6), now()->addDays(7)->addMinute()));

        Mail::assertSent(GroupInvitation::class, fn (GroupInvitation $mail) => $mail->hasTo('newcomer@example.com')
            && $mail->invite->is($invite));
    }

    public function test_the_email_names_the_group_and_links_to_the_invitation(): void
    {
        $invite = $this->inviteFor('newcomer@example.com');

        $mail = (new GroupInvitation($invite->load('group', 'inviter')))->render();

        $this->assertStringContainsString('Fife crew', $mail);
        $this->assertStringContainsString('Alistair Admin', $mail);
        $this->assertStringContainsString(route('invitations.show', $invite->token), $mail);
    }

    public function test_visitor_is_not_an_invitable_role(): void
    {
        Mail::fake();

        Livewire::actingAs($this->admin)->test('pages::group-show', ['group' => $this->group])
            ->set('inviteEmail', 'newcomer@example.com')
            ->set('inviteRole', 'visitor')
            ->call('invite')
            ->assertHasErrors(['inviteRole']);

        $this->assertDatabaseCount('group_invites', 0);
        Mail::assertNothingSent();
    }

    public function test_someone_already_in_the_group_cannot_be_invited(): void
    {
        Mail::fake();
        $member = User::factory()->create(['email' => 'member@example.com']);
        $this->group->users()->attach($member, ['role' => 'member']);

        Livewire::actingAs($this->admin)->test('pages::group-show', ['group' => $this->group])
            ->set('inviteEmail', 'member@example.com')
            ->call('invite')
            ->assertHasErrors(['inviteEmail']);

        Mail::assertNothingSent();
    }

    public function test_a_second_invitation_replaces_the_first(): void
    {
        $first = $this->inviteFor('newcomer@example.com');
        $second = $this->inviteFor('newcomer@example.com', GroupRole::Admin);

        $this->assertNull(GroupInvite::findOpen($first->token));
        $this->assertNotNull(GroupInvite::findOpen($second->token));
        $this->assertDatabaseCount('group_invites', 1);
    }

    public function test_an_admin_can_cancel_an_invitation(): void
    {
        $invite = $this->inviteFor('newcomer@example.com');

        Livewire::actingAs($this->admin)->test('pages::group-show', ['group' => $this->group])
            ->call('cancelInvite', $invite->id);

        $this->get(route('invitations.show', $invite->token))->assertStatus(410);
    }

    public function test_a_stranger_sees_a_form_to_create_an_account_for_the_invited_address(): void
    {
        $invite = $this->inviteFor('newcomer@example.com', GroupRole::Follower);

        $this->get(route('invitations.show', $invite->token))
            ->assertOk()
            ->assertSee('Fife crew')
            ->assertSee('as a follower')
            ->assertSee('newcomer@example.com')
            ->assertSee('Create my account');
    }

    public function test_opening_the_invitation_changes_nothing(): void
    {
        $invite = $this->inviteFor('newcomer@example.com');

        $this->get(route('invitations.show', $invite->token))->assertOk();

        $this->assertDatabaseMissing('users', ['email' => 'newcomer@example.com']);
        $this->assertNull($invite->fresh()->accepted_at);
    }

    public function test_creating_an_account_from_an_invitation_joins_at_the_chosen_role_and_is_already_verified(): void
    {
        $invite = $this->inviteFor('newcomer@example.com', GroupRole::Follower);

        $this->post(route('invitations.register', $invite->token), $this->newAccount())
            ->assertRedirect(route('groups.show', $this->group));

        $user = User::where('email', 'newcomer@example.com')->firstOrFail();
        $this->assertSame('Nell Newcomer', $user->name);
        $this->assertTrue($user->hasVerifiedEmail());
        $this->assertSame(GroupRole::Follower, $this->group->roleOf($user));
        $this->assertNotNull($invite->fresh()->accepted_at);
        $this->assertAuthenticatedAs($user);
    }

    public function test_an_invitation_works_once(): void
    {
        $invite = $this->inviteFor('newcomer@example.com');

        $this->post(route('invitations.register', $invite->token), $this->newAccount());
        auth()->logout();

        $this->get(route('invitations.show', $invite->token))->assertStatus(410);
        $this->post(route('invitations.register', $invite->token), $this->newAccount())->assertStatus(410);

        $this->assertDatabaseCount('users', 2);
    }

    public function test_an_expired_invitation_cannot_be_used(): void
    {
        $invite = $this->inviteFor('newcomer@example.com');
        $invite->update(['expires_at' => now()->subMinute()]);

        $this->get(route('invitations.show', $invite->token))->assertStatus(410);
        $this->post(route('invitations.register', $invite->token), $this->newAccount())->assertStatus(410);

        $this->assertDatabaseMissing('users', ['email' => 'newcomer@example.com']);
    }

    public function test_the_account_is_always_for_the_invited_address(): void
    {
        $invite = $this->inviteFor('newcomer@example.com');

        $this->post(route('invitations.register', $invite->token), [
            ...$this->newAccount(),
            'email' => 'someone-else@example.com',
        ]);

        $this->assertDatabaseHas('users', ['email' => 'newcomer@example.com']);
        $this->assertDatabaseMissing('users', ['email' => 'someone-else@example.com']);
    }

    public function test_someone_with_an_account_is_asked_to_log_in(): void
    {
        User::factory()->create(['email' => 'newcomer@example.com']);
        $invite = $this->inviteFor('newcomer@example.com');

        $this->get(route('invitations.show', $invite->token))
            ->assertOk()
            ->assertSee('You already have an account')
            ->assertDontSee('Create my account');

        $this->post(route('invitations.register', $invite->token), $this->newAccount())->assertForbidden();
    }

    public function test_the_invited_person_accepts_while_signed_in(): void
    {
        $existing = User::factory()->create(['email' => 'newcomer@example.com']);
        $invite = $this->inviteFor('newcomer@example.com', GroupRole::Admin);

        $this->actingAs($existing)->get(route('invitations.show', $invite->token))
            ->assertOk()
            ->assertSee('Accept the invitation');

        $this->actingAs($existing)->post(route('invitations.accept', $invite->token))
            ->assertRedirect(route('groups.show', $this->group));

        $this->assertSame(GroupRole::Admin, $this->group->roleOf($existing));
        $this->assertNotNull($invite->fresh()->accepted_at);
    }

    public function test_someone_signed_in_as_another_address_cannot_accept(): void
    {
        $invite = $this->inviteFor('newcomer@example.com');
        $other = User::factory()->create();

        $this->actingAs($other)->get(route('invitations.show', $invite->token))
            ->assertOk()
            ->assertSee('for another email address');

        $this->actingAs($other)->post(route('invitations.accept', $invite->token))->assertForbidden();

        $this->assertNull($this->group->roleOf($other));
    }

    public function test_accepting_never_demotes_the_only_admin(): void
    {
        $invite = $this->inviteFor($this->admin->email, GroupRole::Follower);

        $this->actingAs($this->admin)->post(route('invitations.accept', $invite->token))
            ->assertRedirect(route('groups.show', $this->group));

        $this->assertSame(GroupRole::Admin, $this->group->roleOf($this->admin));
    }

    public function test_an_existing_account_sees_its_invitations_on_its_groups_page(): void
    {
        $existing = User::factory()->create(['email' => 'newcomer@example.com']);
        $this->inviteFor('newcomer@example.com');

        $this->actingAs($existing)->get(route('groups.index'))
            ->assertOk()
            ->assertSee('Invitations for you')
            ->assertSee('Fife crew');
    }

    public function test_an_unknown_token_is_unavailable(): void
    {
        $this->get(route('invitations.show', 'nonsense'))->assertStatus(410);
    }
}

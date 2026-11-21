<?php

namespace Tests\Feature\Groups;

use App\Enums\GroupRole;
use App\Models\Group;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class GroupJoinLinkTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Group $group;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create();
        $this->group = Group::factory()->create(['name' => 'Fife crew']);
        $this->group->users()->attach($this->admin, ['role' => 'admin']);
        $this->group->makeJoinLink();
    }

    /** @return array<string, string> */
    private function newAccount(string $email = 'newcomer@example.com'): array
    {
        return [
            'name' => 'Nell Newcomer',
            'email' => $email,
            'password' => 'a-long-password-1',
            'password_confirmation' => 'a-long-password-1',
        ];
    }

    public function test_an_admin_can_make_switch_off_and_remake_the_link(): void
    {
        $fresh = Group::factory()->create();
        $fresh->users()->attach($this->admin, ['role' => 'admin']);

        $component = Livewire::actingAs($this->admin)->test('pages::group-show', ['group' => $fresh]);

        $component->call('makeLink');
        $first = $fresh->fresh()->join_token;
        $this->assertNotNull($first);
        $this->assertTrue($fresh->fresh()->joinLinkIsLive());

        $component->call('makeLink');
        $second = $fresh->fresh()->join_token;
        $this->assertNotSame($first, $second);
        $this->get(route('groups.join', $first))->assertStatus(410);

        $component->call('revokeLink');
        $this->assertNull($fresh->fresh()->join_token);
        $this->get(route('groups.join', $second))->assertStatus(410);
    }

    public function test_the_link_lasts_a_week(): void
    {
        $this->assertTrue($this->group->join_token_expires_at->between(now()->addDays(6), now()->addDays(7)->addMinute()));

        $this->travel(8)->days();

        $this->get(route('groups.join', $this->group->join_token))->assertStatus(410);
    }

    public function test_opening_the_link_shows_the_group_and_a_join_button_and_joins_nobody(): void
    {
        $this->get(route('groups.join', $this->group->join_token))
            ->assertOk()
            ->assertSee('Fife crew')
            ->assertSee('Join')
            ->assertDontSee($this->admin->name);

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('group_user', 1);
    }

    public function test_opening_the_link_while_signed_in_joins_nobody_either(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('groups.join', $this->group->join_token))->assertOk();

        $this->assertNull($this->group->roleOf($user));
    }

    public function test_pressing_join_while_signed_in_makes_you_a_visitor(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('groups.join.store', $this->group->join_token))
            ->assertRedirect(route('groups.show', $this->group));

        $this->assertSame(GroupRole::Visitor, $this->group->roleOf($user));
    }

    public function test_someone_with_no_account_creates_one_as_a_visitor_and_must_verify_their_email(): void
    {
        Notification::fake();

        $this->post(route('groups.join.store', $this->group->join_token), $this->newAccount())
            ->assertRedirect(route('verification.notice'));

        $user = User::where('email', 'newcomer@example.com')->firstOrFail();
        $this->assertFalse($user->hasVerifiedEmail());
        $this->assertSame(GroupRole::Visitor, $this->group->roleOf($user));
        $this->assertAuthenticatedAs($user);
        Notification::assertSentTo($user, VerifyEmail::class);

        // Until they verify, they get nowhere.
        $this->get(route('groups.show', $this->group))->assertRedirect(route('verification.notice'));
    }

    public function test_the_account_form_checks_its_fields(): void
    {
        $this->post(route('groups.join.store', $this->group->join_token), [
            'name' => '',
            'email' => 'not-an-email',
            'password' => 'short',
            'password_confirmation' => 'different',
        ])->assertSessionHasErrors(['name', 'email', 'password']);

        $this->assertDatabaseCount('users', 1);
    }

    public function test_an_address_that_already_has_an_account_cannot_register_again(): void
    {
        User::factory()->create(['email' => 'newcomer@example.com']);

        $this->post(route('groups.join.store', $this->group->join_token), $this->newAccount())
            ->assertSessionHasErrors(['email']);

        $this->assertDatabaseCount('users', 2);
    }

    public function test_someone_already_in_the_group_keeps_their_role(): void
    {
        $this->actingAs($this->admin)->post(route('groups.join.store', $this->group->join_token))
            ->assertRedirect(route('groups.show', $this->group));

        $this->assertSame(GroupRole::Admin, $this->group->roleOf($this->admin));
    }

    public function test_at_most_thirty_people_can_wait_for_approval(): void
    {
        User::factory()->count(Group::MAX_WAITING_VISITORS)->create()
            ->each(fn (User $user) => $this->group->users()->attach($user, ['role' => 'visitor']));

        $this->get(route('groups.join', $this->group->join_token))
            ->assertOk()
            ->assertSee('too many people waiting');

        $this->post(route('groups.join.store', $this->group->join_token), $this->newAccount())
            ->assertSessionHasErrors(['email']);

        $this->assertDatabaseMissing('users', ['email' => 'newcomer@example.com']);

        $late = User::factory()->create();
        $this->actingAs($late)->post(route('groups.join.store', $this->group->join_token))
            ->assertSessionHasErrors(['email']);
        $this->assertNull($this->group->roleOf($late));
    }

    public function test_room_returns_once_a_visitor_is_approved_or_declined(): void
    {
        $visitors = User::factory()->count(Group::MAX_WAITING_VISITORS)->create();
        $visitors->each(fn (User $user) => $this->group->users()->attach($user, ['role' => 'visitor']));

        Livewire::actingAs($this->admin)->test('pages::group-show', ['group' => $this->group])
            ->call('approve', $visitors->first()->id)
            ->call('remove', $visitors->last()->id);

        $this->assertTrue($this->group->fresh()->hasRoomForVisitors());
    }

    public function test_the_link_is_rate_limited(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->get(route('groups.join', $this->group->join_token))->assertOk();
        }

        $this->get(route('groups.join', $this->group->join_token))->assertStatus(429);
    }

    public function test_an_unknown_token_is_unavailable(): void
    {
        $this->get(route('groups.join', 'nonsense'))->assertStatus(410);
        $this->post(route('groups.join.store', 'nonsense'), $this->newAccount())->assertStatus(410);

        $this->assertDatabaseMissing('users', ['email' => 'newcomer@example.com']);
    }

    public function test_the_link_page_reveals_nothing_beyond_the_groups_name(): void
    {
        $this->group->users()->attach(User::factory()->create(['name' => 'Bella Walker']), ['role' => 'member']);

        $this->get(route('groups.join', $this->group->join_token))
            ->assertOk()
            ->assertDontSee('Bella Walker')
            ->assertDontSee($this->admin->email);
    }
}

<?php

namespace Tests\Feature\Groups;

use App\Enums\GroupRole;
use App\Mail\VisitorWaiting;
use App\Models\Group;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

class VisitorAlertTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $otherAdmin;

    private Group $group;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create();
        $this->otherAdmin = User::factory()->create();
        $this->group = Group::factory()->create(['name' => 'Fife crew']);
        $this->group->users()->attach($this->admin, ['role' => 'admin']);
        $this->group->users()->attach($this->otherAdmin, ['role' => 'admin']);
        $this->group->makeJoinLink();
    }

    public function test_every_admin_is_emailed_when_a_signed_in_person_joins_by_the_link(): void
    {
        Mail::fake();
        $visitor = User::factory()->create(['name' => 'Vera Visitor', 'email' => 'vera@example.com']);

        $this->actingAs($visitor)->post(route('groups.join.store', $this->group->join_token));

        Mail::assertSent(VisitorWaiting::class, 2);
        Mail::assertSent(VisitorWaiting::class, fn (VisitorWaiting $mail) => $mail->hasTo($this->admin->email));
        Mail::assertSent(VisitorWaiting::class, fn (VisitorWaiting $mail) => $mail->hasTo($this->otherAdmin->email));
    }

    public function test_the_email_names_the_person_and_links_to_the_group_but_approves_nothing(): void
    {
        $visitor = User::factory()->create(['name' => 'Vera Visitor', 'email' => 'vera@example.com']);
        $mail = new VisitorWaiting($this->group, $visitor);

        $mail->assertHasSubject('Vera Visitor is waiting to join Fife crew on '.config('app.name'));
        $mail->assertSeeInHtml('Vera Visitor');
        $mail->assertSeeInHtml('vera@example.com');
        $mail->assertSeeInHtml(route('groups.show', $this->group));
        $mail->assertDontSeeInHtml('invitations/');
        $mail->assertDontSeeInHtml($this->group->join_token);
    }

    public function test_an_admin_is_emailed_when_someone_creates_an_account_through_the_link(): void
    {
        Mail::fake();

        $this->post(route('groups.join.store', $this->group->join_token), [
            'name' => 'Nell Newcomer',
            'email' => 'newcomer@example.com',
            'password' => 'a-long-password-1',
            'password_confirmation' => 'a-long-password-1',
        ]);

        Mail::assertSent(VisitorWaiting::class, 2);
        Mail::assertSent(VisitorWaiting::class, fn (VisitorWaiting $mail) => $mail->visitor->email === 'newcomer@example.com');
    }

    public function test_pressing_join_twice_sends_one_round_of_emails(): void
    {
        Mail::fake();
        $visitor = User::factory()->create();

        $this->actingAs($visitor)->post(route('groups.join.store', $this->group->join_token));
        $this->actingAs($visitor)->post(route('groups.join.store', $this->group->join_token));

        Mail::assertSent(VisitorWaiting::class, 2);
    }

    public function test_a_person_who_is_already_in_the_group_sends_nothing(): void
    {
        Mail::fake();
        $member = User::factory()->create();
        $this->group->users()->attach($member, ['role' => 'member']);

        $this->actingAs($member)->post(route('groups.join.store', $this->group->join_token));

        Mail::assertNothingSent();
    }

    public function test_members_and_followers_are_not_emailed(): void
    {
        Mail::fake();
        $member = User::factory()->create();
        $follower = User::factory()->create();
        $this->group->users()->attach($member, ['role' => 'member']);
        $this->group->users()->attach($follower, ['role' => 'follower']);

        $this->actingAs(User::factory()->create())->post(route('groups.join.store', $this->group->join_token));

        Mail::assertNotSent(VisitorWaiting::class, fn (VisitorWaiting $mail) => $mail->hasTo($member->email) || $mail->hasTo($follower->email));
    }

    public function test_nothing_is_sent_when_the_group_is_full_and_the_person_is_turned_away(): void
    {
        Mail::fake();

        foreach (User::factory()->count(Group::MAX_WAITING_VISITORS)->create() as $waiting) {
            $this->group->users()->attach($waiting, ['role' => 'visitor']);
        }

        $this->actingAs(User::factory()->create())->post(route('groups.join.store', $this->group->join_token));

        Mail::assertNothingSent();
    }

    public function test_a_failing_mailer_does_not_stop_someone_joining(): void
    {
        Mail::shouldReceive('to')->andThrow(new RuntimeException('mail is down'));
        $visitor = User::factory()->create();

        $this->actingAs($visitor)->post(route('groups.join.store', $this->group->join_token))
            ->assertRedirect(route('groups.show', $this->group));

        $this->assertSame(GroupRole::Visitor, $this->group->roleOf($visitor));
    }

    public function test_an_admin_sees_how_many_are_waiting_on_the_groups_list(): void
    {
        foreach (User::factory()->count(2)->create() as $waiting) {
            $this->group->users()->attach($waiting, ['role' => 'visitor']);
        }

        Livewire::actingAs($this->admin)->test('pages::groups')
            ->assertSee('2 waiting')
            ->assertSeeHtml('data-test="waiting-badge"');
    }

    public function test_the_badge_is_not_shown_when_nobody_is_waiting(): void
    {
        Livewire::actingAs($this->admin)->test('pages::groups')
            ->assertDontSeeHtml('data-test="waiting-badge"');
    }

    public function test_a_member_does_not_see_the_badge(): void
    {
        $member = User::factory()->create();
        $this->group->users()->attach($member, ['role' => 'member']);
        $this->group->users()->attach(User::factory()->create(), ['role' => 'visitor']);

        Livewire::actingAs($member)->test('pages::groups')
            ->assertDontSeeHtml('data-test="waiting-badge"');
    }
}

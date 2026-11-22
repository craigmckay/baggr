<?php

namespace Tests\Feature\MyHills;

use App\Actions\Walks\LogWalk;
use App\Models\Group;
use App\Models\Hill;
use App\Models\HillClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MyHillsPagesTest extends TestCase
{
    use RefreshDatabase;

    /** @param  array<string, mixed>  $attributes */
    private function munro(array $attributes = []): Hill
    {
        $hill = Hill::factory()->create($attributes);
        $hill->classes()->attach(HillClass::firstOrCreate(['code' => 'M'], ['name' => 'Munro']));

        return $hill;
    }

    /**
     * Hills made in bulk: the factory's random names run out after a few hundred.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function manyMunros(int $count, array $attributes = []): void
    {
        $class = HillClass::firstOrCreate(['code' => 'M'], ['name' => 'Munro']);

        for ($i = 1; $i <= $count; $i++) {
            $hill = new Hill;
            $hill->forceFill([
                'name' => "Ben Number{$i}",
                'slug' => "ben-number-{$i}",
                'dobih_number' => 9000 + $i,
                'height_m' => 1000 + $i,
                'region' => 'West Highlands',
            ] + $attributes)->save();
            $hill->classes()->attach($class);
        }
    }

    private function walk(User $user, Hill $hill, ?Group $group = null): void
    {
        app(LogWalk::class)->handle($user, [
            'group_id' => $group?->id,
            'people' => [$user->id],
            'days' => [['date' => '2026-10-01', 'direction' => null, 'duration_minutes' => null, 'hills' => [$hill->id]]],
        ]);
    }

    public function test_guests_are_sent_to_sign_in(): void
    {
        $this->get(route('my-hills.index'))->assertRedirect(route('login'));
        $this->get(route('my-hills.todo'))->assertRedirect(route('login'));
    }

    public function test_an_unverified_person_is_sent_to_verify_their_email(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->get(route('my-hills.index'))->assertRedirect(route('verification.notice'));
    }

    public function test_my_hills_shows_how_many_i_have_done_and_the_regions(): void
    {
        $me = User::factory()->create();
        $done = $this->munro(['name' => 'Ben Done', 'mwis_forecast_url' => 'https://www.mwis.org.uk/forecasts/scottish/west-highlands']);
        $this->munro(['name' => 'Ben Todo', 'mwis_forecast_url' => 'https://www.mwis.org.uk/forecasts/scottish/west-highlands']);
        $this->walk($me, $done);

        $this->actingAs($me)->get(route('my-hills.index'))
            ->assertOk()
            ->assertSee('West Highlands')
            ->assertSee('Ben Done')
            ->assertSee('1 / 2', false)
            ->assertSee('50%');

        Livewire::actingAs($me)->test('pages::my-hills')->assertSeeHtml('data-test="done-count"');
    }

    public function test_a_person_with_no_ascents_is_pointed_at_logging_a_walk(): void
    {
        $this->munro();

        $this->actingAs(User::factory()->create())->get(route('my-hills.index'))
            ->assertOk()
            ->assertSee('Nothing here yet');
    }

    public function test_the_list_and_scope_can_be_set_from_the_address(): void
    {
        $me = User::factory()->create();

        Livewire::actingAs($me)->withQueryParams(['list' => 'C', 'scope' => 'solo'])->test('pages::my-hills')
            ->assertSet('list', 'C')
            ->assertSet('scope', 'solo');
    }

    public function test_my_hills_offers_my_groups_as_a_filter(): void
    {
        $me = User::factory()->create();
        $group = Group::factory()->create(['name' => 'Fife Crew']);
        $group->users()->attach($me, ['role' => 'member']);
        Group::factory()->create(['name' => 'Somebody Elses']);

        $this->actingAs($me)->get(route('my-hills.index'))
            ->assertSee('Fife Crew')
            ->assertDontSee('Somebody Elses');
    }

    public function test_changing_the_grouping_is_remembered(): void
    {
        $me = User::factory()->create();

        Livewire::actingAs($me)->test('pages::my-hills')
            ->assertSet('grouping', 'mwis')
            ->set('grouping', 'smc');

        $this->assertSame('smc', $me->fresh()->hill_grouping);

        Livewire::actingAs($me->fresh())->test('pages::my-hills')->assertSet('grouping', 'smc');
        Livewire::actingAs($me->fresh())->test('pages::my-hills-todo')->assertSet('grouping', 'smc');
    }

    public function test_a_made_up_grouping_is_not_saved(): void
    {
        $me = User::factory()->create();

        Livewire::actingAs($me)->test('pages::my-hills')
            ->set('grouping', 'nonsense')
            ->assertSet('grouping', 'mwis');

        $this->assertSame('mwis', $me->fresh()->hill_grouping);
    }

    public function test_the_to_do_list_only_has_hills_i_have_not_done(): void
    {
        $me = User::factory()->create();
        $done = $this->munro(['name' => 'Ben Finished']);
        $this->munro(['name' => 'Ben Waiting']);
        $this->walk($me, $done);

        $this->actingAs($me)->get(route('my-hills.todo'))
            ->assertOk()
            ->assertSee('Ben Waiting')
            ->assertDontSee('Ben Finished')
            ->assertSee('1 to go');
    }

    public function test_the_to_do_list_can_be_sorted_by_height_across_regions(): void
    {
        $me = User::factory()->create();
        $this->munro(['name' => 'Ben Lowest', 'height_m' => 915, 'mwis_forecast_url' => 'https://www.mwis.org.uk/forecasts/scottish/the-northwest-highlands']);
        $this->munro(['name' => 'Ben Highest', 'height_m' => 1300, 'mwis_forecast_url' => 'https://www.mwis.org.uk/forecasts/scottish/southeastern-highlands']);
        $this->munro(['name' => 'Ben Middle', 'height_m' => 1000, 'mwis_forecast_url' => 'https://www.mwis.org.uk/forecasts/scottish/west-highlands']);

        Livewire::actingAs($me)->test('pages::my-hills-todo')
            ->set('sort', 'height')
            ->assertSeeInOrder(['Ben Highest', 'Ben Middle', 'Ben Lowest']);
    }

    public function test_the_to_do_list_offers_to_log_each_hill(): void
    {
        $hill = $this->munro(['name' => 'Ben Logme']);

        $this->actingAs(User::factory()->create())->get(route('my-hills.todo'))
            ->assertSee(route('walks.log', ['hill' => $hill->slug]), false);
    }

    public function test_the_to_do_list_says_so_when_everything_is_done(): void
    {
        $me = User::factory()->create();
        $this->walk($me, $this->munro());

        $this->actingAs($me)->get(route('my-hills.todo'))->assertSee('All done');
    }

    public function test_the_to_do_list_respects_the_solo_filter(): void
    {
        $me = User::factory()->create();
        $group = Group::factory()->create();
        $group->users()->attach($me, ['role' => 'member']);
        $withGroup = $this->munro(['name' => 'Ben Together']);
        $this->walk($me, $withGroup, $group);

        Livewire::actingAs($me)->test('pages::my-hills-todo')->assertDontSee('Ben Together');
        Livewire::actingAs($me)->withQueryParams(['scope' => 'solo'])->test('pages::my-hills-todo')->assertSee('Ben Together');
    }

    public function test_my_hills_only_draws_a_regions_hills_once_it_is_opened(): void
    {
        $me = User::factory()->create();
        $this->munro(['name' => 'Ben Hidden', 'mwis_forecast_url' => 'https://www.mwis.org.uk/forecasts/scottish/west-highlands']);

        Livewire::actingAs($me)->test('pages::my-hills')
            ->assertDontSee('Ben Hidden')
            ->call('toggle', 'mwis-WH')
            ->assertSee('Ben Hidden')
            ->call('toggle', 'mwis-WH')
            ->assertDontSee('Ben Hidden');
    }

    public function test_changing_the_list_shuts_the_open_regions(): void
    {
        Livewire::actingAs(User::factory()->create())->test('pages::my-hills')
            ->call('toggle', 'mwis-WH')
            ->set('list', 'C')
            ->assertSet('open', []);
    }

    public function test_a_long_to_do_list_starts_shut_and_opens_a_region_on_request(): void
    {
        $this->manyMunros(301, ['mwis_forecast_url' => 'https://www.mwis.org.uk/forecasts/scottish/west-highlands']);

        Livewire::actingAs(User::factory()->create())->test('pages::my-hills-todo')
            ->assertDontSee('Ben Number1<', false)
            ->call('toggle', 'mwis-WH')
            ->assertSee('Ben Number1<', false);
    }

    public function test_a_short_to_do_list_starts_open(): void
    {
        $this->munro(['name' => 'Ben Showing', 'mwis_forecast_url' => 'https://www.mwis.org.uk/forecasts/scottish/west-highlands']);

        Livewire::actingAs(User::factory()->create())->test('pages::my-hills-todo')
            ->assertSee('Ben Showing')
            ->call('toggle', 'mwis-WH')
            ->assertDontSee('Ben Showing');
    }

    public function test_the_tallest_first_list_shows_a_hundred_at_a_time(): void
    {
        $this->manyMunros(120);

        Livewire::actingAs(User::factory()->create())->test('pages::my-hills-todo')
            ->set('sort', 'height')
            ->assertSee('Ben Number120<', false)
            ->assertDontSee('Ben Number1<', false)
            ->call('showMore')
            ->assertSee('Ben Number1<', false);
    }

    public function test_the_sidebar_links_to_my_hills(): void
    {
        $this->actingAs(User::factory()->create())->get(route('groups.index'))
            ->assertSee(route('my-hills.index'), false);
    }

    public function test_a_person_can_choose_their_colour(): void
    {
        $me = User::factory()->create();

        $this->assertSame(User::DEFAULT_COLOUR, $me->colour);

        Livewire::actingAs($me)->test('pages::settings.profile')
            ->set('colour', '#C0392B')
            ->call('updateProfileInformation')
            ->assertHasNoErrors();

        $this->assertSame('#C0392B', $me->fresh()->colour);
    }

    public function test_a_colour_outside_the_palette_is_refused(): void
    {
        $me = User::factory()->create();

        Livewire::actingAs($me)->test('pages::settings.profile')
            ->set('colour', '#000000')
            ->call('updateProfileInformation')
            ->assertHasErrors(['colour']);

        $this->assertSame(User::DEFAULT_COLOUR, $me->fresh()->colour);
    }
}

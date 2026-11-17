<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Hill;
use App\Models\HillClass;
use App\Models\HillName;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class HillsTest extends TestCase
{
    use RefreshDatabase;

    /** A hill on the given DoBIH list (M for Munros, C for Corbetts...). */
    private function hillOn(string $code, array $attributes = []): Hill
    {
        $class = HillClass::firstOrCreate(['code' => $code], ['name' => $code, 'show_in_list' => true, 'sort_order' => 1]);
        $hill = Hill::factory()->create($attributes);
        $hill->classes()->attach($class);

        return $hill;
    }

    public function test_guests_can_view_the_hill_browser(): void
    {
        $this->hillOn('M', ['name' => 'Ben Nevis']);

        $response = $this->get(route('hills.index'));

        $response->assertOk();
        $response->assertSee('Ben Nevis');
    }

    public function test_it_shows_munros_by_default(): void
    {
        $this->hillOn('M', ['name' => 'Ben Nevis']);
        $this->hillOn('C', ['name' => 'Ben Cleuch']);

        Livewire::test('pages::hills')
            ->assertSet('list', 'M')
            ->assertSee('Ben Nevis')
            ->assertDontSee('Ben Cleuch');
    }

    public function test_the_list_can_be_changed(): void
    {
        $this->hillOn('M', ['name' => 'Ben Nevis']);
        $this->hillOn('C', ['name' => 'Ben Cleuch']);
        Hill::factory()->create(['name' => 'Dumyat']);

        Livewire::test('pages::hills')
            ->set('list', 'C')
            ->assertSee('Ben Cleuch')
            ->assertDontSee('Ben Nevis')
            ->assertDontSee('Dumyat')
            ->set('list', 'all')
            ->assertSee('Ben Nevis')
            ->assertSee('Ben Cleuch')
            ->assertSee('Dumyat');
    }

    public function test_hills_can_be_searched_by_name_or_region(): void
    {
        $this->hillOn('M', ['name' => 'Ben Nevis', 'region' => 'West Highlands']);
        $this->hillOn('M', ['name' => 'Ben Macdui', 'region' => 'Cairngorms NP and Monadhliath']);

        Livewire::test('pages::hills')
            ->set('search', 'Macdui')
            ->assertSee('Ben Macdui')
            ->assertDontSee('Ben Nevis');
    }

    public function test_hills_can_be_searched_by_alternate_name_or_area(): void
    {
        $nevis = $this->hillOn('M', ['name' => 'Ben Nevis']);
        HillName::create(['hill_id' => $nevis->id, 'name' => 'Beinn Nibheis', 'sort_order' => 2]);

        $cleuch = $this->hillOn('M', ['name' => 'Ben Cleuch']);
        $area = Area::create(['code' => 'D002', 'short_name' => 'Ochils', 'name' => 'Ochil Hills', 'type' => 'region', 'country' => 'S']);
        $cleuch->areas()->attach($area);

        Livewire::test('pages::hills')
            ->set('search', 'Nibheis')
            ->assertSee('Ben Nevis')
            ->assertDontSee('Ben Cleuch')
            ->set('search', 'Ochil')
            ->assertSee('Ben Cleuch')
            ->assertDontSee('Ben Nevis');
    }

    public function test_a_hill_without_a_region_shows_its_country(): void
    {
        $this->hillOn('M', ['name' => 'Ben Nevis', 'region' => null, 'country' => 'S']);

        Livewire::test('pages::hills')->assertSee('Scotland');
    }

    public function test_hills_can_be_sorted_by_column(): void
    {
        $this->hillOn('M', ['name' => 'Ben Nevis', 'height_m' => 1345]);
        $this->hillOn('M', ['name' => 'Ben Macdui', 'height_m' => 1309]);

        $component = Livewire::test('pages::hills')
            ->call('sort', 'height_m')
            ->call('sort', 'height_m');

        $this->assertEquals('height_m', $component->get('sortBy'));
        $this->assertEquals('desc', $component->get('sortDirection'));
        $this->assertEquals('Ben Nevis', $component->get('hills')->first()->name);
    }

    public function test_it_pages_through_fifty_hills_at_a_time(): void
    {
        Hill::factory()->count(60)->create();

        $component = Livewire::test('pages::hills')->set('list', 'all');

        $this->assertCount(50, $component->get('hills')->items());
        $this->assertSame(60, $component->get('hills')->total());

        $component->call('gotoPage', 2, 'page');

        $this->assertCount(10, $component->get('hills')->items());
    }

    public function test_changing_the_list_returns_to_the_first_page(): void
    {
        Hill::factory()->count(60)->create();

        Livewire::test('pages::hills')
            ->set('list', 'all')
            ->call('gotoPage', 2, 'page')
            ->set('list', 'M')
            ->assertSet('paginators.page', 1);
    }

    public function test_each_row_links_through_to_its_hill_page(): void
    {
        $hill = $this->hillOn('M', ['name' => 'Ben Nevis']);

        Livewire::test('pages::hills')
            ->assertSee(route('hills.show', $hill));
    }

    public function test_it_credits_dobih(): void
    {
        $this->get(route('hills.index'))->assertSee('Database of British and Irish Hills');
    }
}

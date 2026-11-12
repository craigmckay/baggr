<?php

namespace Tests\Feature;

use App\Models\Hill;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class HillsTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_can_view_the_hill_browser(): void
    {
        Hill::factory()->create(['name' => 'Ben Nevis']);

        $response = $this->get(route('hills.index'));

        $response->assertOk();
        $response->assertSee('Ben Nevis');
    }

    public function test_hills_can_be_searched_by_name_or_region(): void
    {
        Hill::factory()->create(['name' => 'Ben Nevis', 'region' => 'West Highlands']);
        Hill::factory()->create(['name' => 'Ben Macdui', 'region' => 'Cairngorms NP and Monadhliath']);

        Livewire::test('pages::hills')
            ->set('search', 'Macdui')
            ->assertSee('Ben Macdui')
            ->assertDontSee('Ben Nevis');
    }

    public function test_hills_can_be_sorted_by_column(): void
    {
        Hill::factory()->create(['name' => 'Ben Nevis', 'height_m' => 1345]);
        Hill::factory()->create(['name' => 'Ben Macdui', 'height_m' => 1309]);

        $component = Livewire::test('pages::hills')
            ->call('sort', 'height_m')
            ->call('sort', 'height_m');

        $this->assertEquals('height_m', $component->get('sortBy'));
        $this->assertEquals('desc', $component->get('sortDirection'));
        $this->assertEquals('Ben Nevis', $component->get('hills')->first()->name);
    }

    public function test_each_row_links_through_to_its_hill_page(): void
    {
        $hill = Hill::factory()->create(['name' => 'Ben Nevis']);

        Livewire::test('pages::hills')
            ->assertSee(route('hills.show', $hill));
    }
}

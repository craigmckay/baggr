<?php

namespace Tests\Feature;

use App\Models\Hill;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DobihCreditTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_page_showing_hill_data_credits_dobih(): void
    {
        $hill = Hill::factory()->create();

        foreach (['/', route('hills.index'), route('hills.show', $hill)] as $url) {
            $this->get($url)
                ->assertOk()
                ->assertSee('Hill data:')
                ->assertSee('Database of British and Irish Hills v'.config('dobih.version'))
                ->assertSee('https://www.hill-bagging.co.uk/dobih', false)
                ->assertSee('CC BY 4.0');
        }
    }
}

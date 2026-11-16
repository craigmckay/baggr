<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_redirects_to_the_home_page(): void
    {
        $this->get(route('dashboard'))->assertRedirect('/');

        $this->actingAs(User::factory()->create())
            ->get(route('dashboard'))
            ->assertRedirect('/');
    }
}

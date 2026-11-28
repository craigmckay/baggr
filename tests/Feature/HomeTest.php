<?php

namespace Tests\Feature;

use App\Models\Hill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomeTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_see_the_landing_page(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Get yourselves together.')
            ->assertSee('The hills are calling.')
            ->assertSee('Sign in')
            ->assertSee(route('login'), false)
            ->assertDontSee('Register')
            ->assertDontSee('/register', false);
    }

    public function test_the_landing_page_shows_no_hill_data(): void
    {
        Hill::factory()->create(['name' => 'Ben Nevis']);

        $this->get('/')->assertDontSee('Ben Nevis');
    }

    public function test_signed_in_users_see_the_dashboard(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/')
            ->assertOk()
            ->assertSee('This weekend')
            ->assertSee('Nothing logged yet.');
    }

    public function test_someone_who_has_not_verified_their_email_is_sent_to_the_hill_list(): void
    {
        $this->actingAs(User::factory()->unverified()->create())
            ->get('/')
            ->assertRedirect(route('hills.index'));
    }

    public function test_the_app_is_named_baggr(): void
    {
        $this->assertSame('baggr', config('app.name'));
    }
}

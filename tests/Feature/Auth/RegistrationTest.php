<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_is_switched_off(): void
    {
        $this->get('/register')->assertNotFound();

        $this->post('/register', [
            'name' => 'John Doe',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertNotFound();

        $this->assertGuest();
        $this->assertDatabaseCount(User::class, 0);
    }

    public function test_the_login_page_does_not_link_to_registration(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertDontSee('/register', false)
            ->assertSee('Invite only');
    }
}

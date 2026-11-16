<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CreateUserTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_verified_user_with_the_given_password(): void
    {
        $this->artisan('users:create', ['name' => 'Craig', 'email' => 'craig@example.com', '--password' => 'a-long-enough-password'])
            ->expectsOutputToContain('Created craig@example.com.')
            ->assertSuccessful();

        $user = User::where('email', 'craig@example.com')->firstOrFail();

        $this->assertTrue(Hash::check('a-long-enough-password', $user->password));
        $this->assertTrue($user->hasVerifiedEmail());
    }

    public function test_it_generates_and_prints_a_password_when_none_is_given(): void
    {
        $this->artisan('users:create', ['name' => 'Craig', 'email' => 'craig@example.com'])
            ->expectsOutputToContain('Password: ')
            ->assertSuccessful();

        $this->assertDatabaseHas('users', ['email' => 'craig@example.com']);
    }

    public function test_it_refuses_a_duplicate_email_or_a_weak_password(): void
    {
        User::factory()->create(['email' => 'craig@example.com']);

        $this->artisan('users:create', ['name' => 'Craig', 'email' => 'craig@example.com'])->assertFailed();
        $this->artisan('users:create', ['name' => 'Sam', 'email' => 'sam@example.com', '--password' => 'short'])->assertFailed();

        $this->assertDatabaseMissing('users', ['email' => 'sam@example.com']);
    }
}

<?php

namespace Tests\Feature;

use App\Mail\TestMessage;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SendTestMailTest extends TestCase
{
    public function test_it_sends_a_test_email(): void
    {
        Mail::fake();

        $this->artisan('mail:test', ['address' => 'friend@example.com'])->assertSuccessful();

        Mail::assertSent(TestMessage::class, fn (TestMessage $mail) => $mail->hasTo('friend@example.com'));
    }

    public function test_it_rejects_something_that_is_not_an_email_address(): void
    {
        Mail::fake();

        $this->artisan('mail:test', ['address' => 'not-an-address'])
            ->expectsOutputToContain('is not an email address')
            ->assertFailed();

        Mail::assertNothingSent();
    }

    public function test_it_warns_when_mail_is_only_being_logged(): void
    {
        config(['mail.default' => 'log']);

        $this->artisan('mail:test', ['address' => 'friend@example.com'])
            ->expectsOutputToContain('nothing will be delivered')
            ->assertSuccessful();
    }
}

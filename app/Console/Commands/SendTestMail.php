<?php

namespace App\Console\Commands;

use App\Mail\TestMessage;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

#[Signature('mail:test {address : Where to send the test email}')]
#[Description('Send a one-line test email, to check the mail settings')]
class SendTestMail extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $address = (string) $this->argument('address');

        if (filter_var($address, FILTER_VALIDATE_EMAIL) === false) {
            $this->error("'{$address}' is not an email address.");

            return self::FAILURE;
        }

        $mailer = config('mail.default');

        if (in_array($mailer, ['log', 'array'], true)) {
            $this->warn("MAIL_MAILER is '{$mailer}', so nothing will be delivered. Set it to a real mailer first.");
        }

        try {
            Mail::to($address)->send(new TestMessage);
        } catch (\Throwable $e) {
            $this->error('Sending failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info("Sent a test email to {$address} using the '{$mailer}' mailer, from ".config('mail.from.address').'.');

        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use App\Concerns\ProfileValidationRules;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

#[Signature('users:create {name} {email} {--password= : Use this password instead of a generated one}')]
#[Description('Create a user, as public registration is switched off')]
class CreateUser extends Command
{
    use ProfileValidationRules;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $generated = blank($this->option('password'));
        $password = $generated ? Str::password(16, symbols: false) : (string) $this->option('password');

        $validator = Validator::make([
            'name' => $this->argument('name'),
            'email' => $this->argument('email'),
            'password' => $password,
        ], [
            ...$this->profileRules(),
            'password' => ['required', 'string', 'min:12'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = new User;
        $user->forceFill([
            'name' => $this->argument('name'),
            'email' => $this->argument('email'),
            'password' => $password,
            'email_verified_at' => now(),
        ])->save();

        $this->info("Created {$user->email}.");

        if ($generated) {
            $this->line("Password: {$password}");
        }

        return self::SUCCESS;
    }
}

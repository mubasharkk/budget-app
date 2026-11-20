<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

/**
 * Sets a new password for a user, found by email or id. Without --password it
 * asks for one (hidden), or makes a random one with --generate. Signs the user
 * out of "remember me" sessions.
 */
class ResetUserPassword extends Command
{
    protected $signature = 'user:reset-password
        {user : The user\'s email address or id}
        {--password= : The new password (otherwise asked for)}
        {--generate : Make a random password and print it}';

    protected $description = 'Reset a user\'s password';

    public function handle(): int
    {
        $identifier = (string) $this->argument('user');
        $user = User::query()
            ->where('email', $identifier)
            ->when(ctype_digit($identifier), fn ($query) => $query->orWhere('id', (int) $identifier))
            ->first();

        if ($user === null) {
            $this->error("No user found for \"{$identifier}\".");

            return self::FAILURE;
        }

        $password = match (true) {
            $this->option('generate') => Str::password(16),
            filled($this->option('password')) => (string) $this->option('password'),
            default => $this->askForPassword(),
        };

        if ($password === null) {
            return self::FAILURE;
        }

        $validator = Validator::make(['password' => $password], ['password' => ['required', Password::defaults()]]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        $user->forceFill([
            'password' => $password,
            'remember_token' => Str::random(60),
        ])->save();

        $this->info("Password reset for {$user->email}.");

        if ($this->option('generate')) {
            $this->line("New password: {$password}");
        }

        return self::SUCCESS;
    }

    private function askForPassword(): ?string
    {
        $password = (string) $this->secret('New password');

        if ($password !== (string) $this->secret('Repeat the new password')) {
            $this->error('The passwords do not match.');

            return null;
        }

        return $password;
    }
}

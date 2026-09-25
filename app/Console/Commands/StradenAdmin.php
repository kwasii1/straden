<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class StradenAdmin extends Command
{
    protected $signature = 'straden:admin {email : Email of the account to create or promote} {--name= : Name for a new account} {--password= : Password to set (a random one is generated when omitted)}';

    protected $description = 'Create an admin account, or promote an existing user to admin and reset their password (account recovery)';

    public function handle(): int
    {
        $email = (string) $this->argument('email');

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->components->error('Please provide a valid email address.');

            return self::FAILURE;
        }

        $password = (string) ($this->option('password') ?: Str::password(20, symbols: false));

        $user = User::query()->firstOrNew(['email' => $email]);
        $created = ! $user->exists;

        if ($created) {
            $user->name = (string) ($this->option('name') ?: Str::before($email, '@'));
        }

        $user->password = $password;
        $user->forceFill(['is_admin' => true, 'email_verified_at' => $user->email_verified_at ?? now()])->save();

        $this->components->info($created ? "Created admin {$email}." : "Promoted {$email} to admin and reset their password.");

        if (! $this->option('password')) {
            $this->components->twoColumnDetail('Password', $password);
        }

        return self::SUCCESS;
    }
}

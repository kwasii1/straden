<?php

namespace App\Console\Commands;

use App\Models\User;
use Database\Seeders\InfluxDbConnectorSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class StradenBootstrap extends Command
{
    protected $signature = 'straden:bootstrap';

    protected $description = 'Prepare a fresh Straden instance: ensure the built-in InfluxDB connector and create the admin from STRADEN_ADMIN_* env on first boot';

    public function handle(): int
    {
        $this->callSilently('db:seed', ['--class' => InfluxDbConnectorSeeder::class, '--force' => true]);
        $this->components->info('Built-in InfluxDB connector is configured.');

        if (User::query()->exists()) {
            return self::SUCCESS;
        }

        $email = config('straden.admin.email');
        $password = config('straden.admin.password');

        if (blank($email) || blank($password)) {
            $this->components->info('No users yet. Open Straden in your browser to create the admin account.');

            return self::SUCCESS;
        }

        $validator = Validator::make(
            ['email' => $email, 'password' => $password],
            ['email' => ['required', 'email', 'max:255'], 'password' => ['required', 'string', Password::min(8)]],
        );

        if ($validator->fails()) {
            $this->components->error('Invalid STRADEN_ADMIN_* values: '.implode(' ', $validator->errors()->all()));

            return self::FAILURE;
        }

        $user = User::create([
            'name' => config('straden.admin.name') ?: 'Admin',
            'email' => $email,
            'password' => $password,
        ]);

        $user->forceFill(['is_admin' => true, 'email_verified_at' => now()])->save();

        $this->components->info("Created admin account {$email}. You can remove STRADEN_ADMIN_PASSWORD from your environment now.");

        return self::SUCCESS;
    }
}

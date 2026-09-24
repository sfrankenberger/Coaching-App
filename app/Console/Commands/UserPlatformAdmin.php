<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Plattform-Admin setzen oder entziehen (nur Sebastian).
 *   php84 artisan user:platform-admin mail@example.com [--name="Name"] [--revoke]
 */
class UserPlatformAdmin extends Command
{
    protected $signature = 'user:platform-admin {email} {--name=} {--revoke}';

    protected $description = 'Macht eine Person zum Plattform-Admin (oder nimmt es zurueck)';

    public function handle(): int
    {
        $email = Str::lower(trim($this->argument('email')));

        $user = User::firstOrCreate(['email' => $email], [
            'name' => $this->option('name') ?: Str::before($email, '@'),
            'email_verified_at' => now(),
        ]);

        $user->forceFill(['is_platform_admin' => ! $this->option('revoke')])->save();

        $this->info($user->is_platform_admin ? "{$email} ist Plattform-Admin." : "{$email} ist kein Plattform-Admin mehr.");

        return self::SUCCESS;
    }
}

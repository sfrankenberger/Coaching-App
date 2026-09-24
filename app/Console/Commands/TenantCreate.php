<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class TenantCreate extends Command
{
    protected $signature = 'tenant:create {slug} {name} {domain} {--owner-email=} {--owner-name=}';

    protected $description = 'Legt einen neuen Mandanten mit Domain und optional Inhaberin an';

    public function handle(): int
    {
        $tenant = Tenant::firstOrCreate(['slug' => Str::slug($this->argument('slug'))], ['name' => $this->argument('name')]);
        $tenant->domains()->firstOrCreate(['domain' => strtolower($this->argument('domain'))], ['is_primary' => true]);

        if ($email = $this->option('owner-email')) {
            $user = User::firstOrCreate(['email' => strtolower($email)], [
                'name' => $this->option('owner-name') ?: $email,
                'password' => Str::random(40),
            ]);
            $tenant->users()->syncWithoutDetaching([$user->id => ['role' => Role::Owner->value, 'status' => 'active', 'joined_at' => now()]]);
        }

        $this->info("Mandant {$tenant->slug} (#{$tenant->id}) bereit.");

        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/** API-Token fuer eine Person: php84 artisan api:token mail@example.com --name=Handy */
class ApiToken extends Command
{
    protected $signature = 'api:token {email} {--name=api : Bezeichnung des Tokens} {--mcp : Auch fuer den MCP-Server (Claude, ChatGPT)}';

    protected $description = 'Sanctum-Token fuer die JSON-API anlegen';

    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();
        if (! $user) {
            $this->error('Keine Person mit dieser Adresse.');

            return self::FAILURE;
        }
        $this->line($user->createToken((string) $this->option('name'), $this->option('mcp') ? ['lesen', 'mcp'] : ['lesen'])->plainTextToken);

        return self::SUCCESS;
    }
}

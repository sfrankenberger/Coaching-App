<?php

namespace App\Tenancy;

use App\Models\Tenant;

/**
 * Haelt den Mandanten der laufenden Anfrage bzw. des laufenden Jobs.
 * Als Singleton im Container registriert (AppServiceProvider).
 */
class CurrentTenant
{
    protected ?Tenant $tenant = null;

    /** Zuletzt gesetzte Adresse fuer Links aus Kommandos und Jobs. */
    protected ?string $wurzel = null;

    public function set(?Tenant $tenant): void
    {
        $this->tenant = $tenant;
    }

    public function get(): ?Tenant
    {
        return $this->tenant;
    }

    public function id(): ?int
    {
        return $this->tenant?->id;
    }

    public function check(): bool
    {
        return $this->tenant !== null;
    }

    public function getOrFail(): Tenant
    {
        return $this->tenant ?? throw new \RuntimeException('Kein Mandant gesetzt.');
    }

    /**
     * Code im Kontext eines bestimmten Mandanten ausfuehren (Jobs, Kommandos, Importe).
     */
    public function run(Tenant $tenant, callable $callback): mixed
    {
        $previous = $this->tenant;
        $this->tenant = $tenant;
        $vorherigeWurzel = $this->wurzel;
        $this->wurzelSetzen($tenant);

        try {
            return $callback($tenant);
        } finally {
            $this->tenant = $previous;
            $this->wurzel = $vorherigeWurzel;
            if (app()->runningInConsole()) {
                app('url')->forceRootUrl($vorherigeWurzel);
            }
        }
    }

    /**
     * In Kommandos und Jobs gibt es keine Anfrage: Links (Mails, Push) sollen trotzdem auf die
     * Domain des Mandanten zeigen, nicht auf APP_URL.
     */
    protected function wurzelSetzen(Tenant $tenant): void
    {
        if (! app()->runningInConsole() || ! ($domain = $tenant->primaryDomain())) {
            return;
        }
        $schema = parse_url((string) config('app.url'), PHP_URL_SCHEME) ?: 'https';
        $this->wurzel = $schema.'://'.$domain;
        app('url')->forceRootUrl($this->wurzel);
    }
}

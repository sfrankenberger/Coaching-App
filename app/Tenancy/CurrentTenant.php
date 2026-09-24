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

        try {
            return $callback($tenant);
        } finally {
            $this->tenant = $previous;
        }
    }
}

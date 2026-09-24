<?php

namespace App\Jobs;

use App\Ai\Summarizer;
use App\Models\Tenant;
use App\Tenancy\CurrentTenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/** Themen und Themenfinder-Text fuer einen Inhalt (Morph-Typ und ID) in der Queue. */
class ProfileContent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $timeout = 300;

    public int $tries = 1;

    public function __construct(public int $tenantId, public string $type, public int $id) {}

    public function handle(CurrentTenant $current, Summarizer $summarizer): void
    {
        $tenant = Tenant::find($this->tenantId);
        $class = Relation::getMorphedModel($this->type);
        if (! $tenant || ! $class) {
            return;
        }
        $current->run($tenant, function () use ($summarizer, $class) {
            if ($model = $class::find($this->id)) {
                $summarizer->finder($model);
            }
        });
    }
}

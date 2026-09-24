<?php

namespace App\Jobs;

use App\Ai\Summarizer;
use App\Models\PodcastEpisode;
use App\Models\Tenant;
use App\Tenancy\CurrentTenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/** Kapitel, FAQ, Zusammenfassung und Schlagworte einer Podcastfolge in der Queue. */
class PrepareEpisode implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $timeout = 600;

    public int $tries = 1;

    public function __construct(public int $tenantId, public int $episodeId, public ?int $requestedBy = null) {}

    public function handle(CurrentTenant $current, Summarizer $summarizer): void
    {
        $tenant = Tenant::find($this->tenantId);
        if (! $tenant) {
            return;
        }
        $current->run($tenant, function () use ($summarizer) {
            if ($e = PodcastEpisode::find($this->episodeId)) {
                $summarizer->episode($e, $this->requestedBy);
            }
        });
    }
}

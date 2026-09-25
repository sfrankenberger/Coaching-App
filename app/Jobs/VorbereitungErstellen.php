<?php

namespace App\Jobs;

use App\Ai\Summarizer;
use App\Filament\Coach\Resources\Memberships\MembershipResource;
use App\Models\Membership;
use App\Models\Tenant;
use App\Notifications\Nachricht;
use App\Notifications\Notifier;
use App\Tenancy\CurrentTenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/** KI-Vorbereitung auf ein Gespraech in der Queue, im Mandanten der Person. */
class VorbereitungErstellen implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $timeout = 300;

    public int $tries = 1;

    public function __construct(public int $tenantId, public int $membershipId, public ?int $requestedBy = null) {}

    public function handle(CurrentTenant $current, Summarizer $summarizer, Notifier $notifier): void
    {
        $tenant = Tenant::find($this->tenantId);
        if (! $tenant) {
            return;
        }
        $current->run($tenant, function () use ($summarizer, $notifier) {
            $m = Membership::with('user')->find($this->membershipId);
            if (! $m?->user) {
                return;
            }
            $v = $summarizer->vorbereitung($m, $this->requestedBy);
            if ($this->requestedBy) {
                $notifier->send([$this->requestedBy], new Nachricht(
                    titel: $v->isDone() ? 'Vorbereitung fertig: '.$m->user->name : 'Vorbereitung nicht möglich: '.$m->user->name,
                    text: $v->isDone() ? 'Du findest sie im Dossier.' : (string) $v->error,
                    url: MembershipResource::getUrl('dossier', ['record' => $m], panel: 'coach'),
                    anlass: 'system',
                    tag: 'ki-vorbereitung-'.$m->id,
                    mailWennKeinPush: false,
                ));
            }
        });
    }
}

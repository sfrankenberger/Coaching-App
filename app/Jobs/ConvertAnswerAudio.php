<?php

namespace App\Jobs;

use App\Models\Answer;
use App\Models\Tenant;
use App\Tenancy\CurrentTenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Storage;

/** Aufnahme aus einer Uebung (Arbeitsbuch) nach m4a wandeln, damit sie ueberall laeuft. */
class ConvertAnswerAudio implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $timeout = 300;

    public function __construct(public int $tenantId, public int $answerId) {}

    public function handle(CurrentTenant $current): void
    {
        $tenant = Tenant::find($this->tenantId);
        if (! $tenant) {
            return;
        }
        $current->run($tenant, function () {
            $a = Answer::find($this->answerId);
            $pfad = $a?->value['v'] ?? null;
            if (! is_string($pfad) || ! ConvertAudio::noetig($pfad)) {
                return;
            }
            if ($neu = ConvertAudio::m4a($pfad)) {
                $a->value = ['v' => $neu];
                $a->saveQuietly();
                Storage::delete($pfad);
            }
        });
    }
}

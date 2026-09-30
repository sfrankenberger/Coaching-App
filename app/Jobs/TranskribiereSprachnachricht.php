<?php

namespace App\Jobs;

use App\Audio\Transkript;
use App\Models\Message;
use App\Models\Tenant;
use App\Tenancy\CurrentTenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/** Sprachnachricht abschreiben, sobald sie da ist (und nach der Umwandlung nach m4a). Fehler nur ins Log. */
class TranskribiereSprachnachricht implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public function __construct(public int $tenantId, public int $messageId) {}

    public function handle(CurrentTenant $current, Transkript $transkript): void
    {
        $tenant = Tenant::find($this->tenantId);
        if (! $tenant) {
            return;
        }
        $current->run($tenant, function () use ($transkript) {
            $msg = Message::find($this->messageId);
            if (! $msg || ! $msg->audio_path || filled($msg->transcript)) {
                return;
            }
            try {
                $text = $transkript->erstellen($msg->audio_path);
            } catch (\Throwable $e) {
                Log::warning('Transkript fehlgeschlagen fuer Nachricht '.$msg->id.': '.$e->getMessage());

                return;
            }
            if ($text) {
                $msg->forceFill(['transcript' => $text])->saveQuietly();
            }
        });
    }
}

<?php

namespace App\Console\Commands;

use App\Jobs\ConvertAudio;
use App\Models\Message;
use App\Models\Tenant;
use App\Tenancy\CurrentTenant;
use Illuminate\Console\Command;

/** Bestehende Sprachnachrichten (webm, ogg) nach m4a wandeln. */
class AudioUmwandeln extends Command
{
    protected $signature = 'audio:umwandeln {tenant : Slug des Mandanten}';

    protected $description = 'Sprachnachrichten nach m4a wandeln, damit sie auf iPhone und Mac laufen';

    public function handle(CurrentTenant $current): int
    {
        $tenant = Tenant::where('slug', $this->argument('tenant'))->firstOrFail();

        $n = $current->run($tenant, function () use ($tenant) {
            $n = 0;
            foreach (Message::whereNotNull('audio_path')->get(['id', 'audio_path']) as $m) {
                if (ConvertAudio::noetig($m->audio_path)) {
                    ConvertAudio::dispatchSync($tenant->id, $m->id);
                    $n++;
                }
            }

            return $n;
        });
        $this->info("{$n} Sprachnachrichten bearbeitet.");

        return self::SUCCESS;
    }
}

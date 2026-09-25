<?php

namespace App\Jobs;

use App\Models\Message;
use App\Models\Tenant;
use App\Tenancy\CurrentTenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

/**
 * Sprachnachrichten aus Chrome und Firefox (webm, ogg) nach m4a (AAC) wandeln,
 * damit sie auch auf iPhone und Mac laufen. Braucht ffmpeg auf dem Server.
 */
class ConvertAudio implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $timeout = 300;

    public int $tries = 2;

    public function __construct(public int $tenantId, public int $messageId) {}

    public function handle(CurrentTenant $current): void
    {
        $tenant = Tenant::find($this->tenantId);
        if (! $tenant) {
            return;
        }
        $current->run($tenant, function () {
            $msg = Message::find($this->messageId);
            if (! $msg || ! $msg->audio_path || str_ends_with($msg->audio_path, '.m4a')) {
                return;
            }
            $ein = Storage::path($msg->audio_path);
            if (! is_file($ein)) {
                return;
            }
            $ziel = preg_replace('~\.[a-z0-9]+$~i', '', $msg->audio_path).'.m4a';
            $aus = Storage::path($ziel);

            $r = Process::timeout(240)->run([config('services.ffmpeg.bin', 'ffmpeg'), '-y', '-loglevel', 'error', '-i', $ein, '-vn', '-c:a', 'aac', '-b:a', '64k', '-movflags', '+faststart', $aus]);
            if (! $r->successful() || ! is_file($aus) || filesize($aus) < 100) {
                Log::warning('Sprachnachricht nicht umgewandelt', ['message' => $msg->id, 'fehler' => mb_substr($r->errorOutput(), 0, 500)]);
                @unlink($aus);

                return;
            }
            $alt = $msg->audio_path;
            $msg->forceFill(['audio_path' => $ziel])->saveQuietly();
            Storage::delete($alt);
        });
    }

    /** Braucht diese Datei eine Umwandlung? */
    public static function noetig(?string $pfad): bool
    {
        return (bool) preg_match('~\.(webm|ogg|oga|opus)$~i', (string) $pfad);
    }
}

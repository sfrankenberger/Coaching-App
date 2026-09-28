<?php

namespace App\Ai\Werkzeuge;

use App\Chat\Terminvorschlag;
use App\Models\User;
use App\Tenancy\CurrentTenant;
use Illuminate\Support\Carbon;

class ZeitenVorschlagen extends Werkzeug
{
    public function __construct(protected Terminvorschlag $vorschlag, protected CurrentTenant $current) {}

    public function name(): string
    {
        return 'zeiten_vorschlagen';
    }

    public function beschreibung(): string
    {
        return 'Einer Person mehrere Zeiten für ein Gespräch vorschlagen. Sie sieht die Zeiten im Gespräch und tippt eine an, daraus wird der Termin.';
    }

    public function schreibt(): bool
    {
        return true;
    }

    public function schema(): array
    {
        return $this->str($this->wer() + [
            'zeiten' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'bis zu 6 Zeiten, JJJJ-MM-TT HH:MM in Ortszeit'],
            'dauer' => ['type' => 'integer', 'description' => 'Minuten, Vorgabe 60'],
            'text' => ['type' => 'string', 'description' => 'Nachricht dazu'],
        ], ['zeiten']);
    }

    public function ausfuehren(array $args, User $von): array
    {
        $m = $this->person($args);
        $tz = $this->current->get()?->timezone ?: config('app.timezone');
        $zeiten = collect((array) ($args['zeiten'] ?? []))->filter()->take(6)->map(fn ($z) => Carbon::parse($z, $tz)->toIso8601String())->all();
        $msg = $this->vorschlag->vorschlagen($von, $m->user, $zeiten, (int) ($args['dauer'] ?? 60), $args['text'] ?? null);

        return ['ok' => true, 'message_id' => $msg->id, 'zeiten' => $msg->vorschlaege()->map(fn ($z) => $z->translatedFormat('D j. M, H:i'))->all()] + $this->kurz($m);
    }
}

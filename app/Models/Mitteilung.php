<?php

namespace App\Models;

use App\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Notifications\DatabaseNotification;

/**
 * Mitteilung in der App (Glocke): jede Nachricht aus dem Notifier landet hier, unabhaengig davon,
 * ob sie auch per Push, Telegram oder Mail ging. Tabelle notifications (Laravel-Standard) mit tenant_id.
 */
class Mitteilung extends DatabaseNotification
{
    use BelongsToTenant;

    protected $table = 'notifications';

    public function titel(): string
    {
        return (string) ($this->data['titel'] ?? '');
    }

    public function text(): string
    {
        return (string) ($this->data['text'] ?? '');
    }

    public function url(): ?string
    {
        return $this->data['url'] ?? null;
    }

    public function icon(): string
    {
        return match ($this->data['anlass'] ?? '') {
            'chat' => 'comments', 'termin', 'termin_neu', 'buchung' => 'calendar', 'aufzeichnung' => 'circle-play',
            'aufgabe', 'aufgabe_erinnerung' => 'list-check', 'kommentar' => 'comment', 'frage' => 'circle-question',
            'material' => 'folder-open', 'abendmail' => 'moon', default => 'bell',
        };
    }
}

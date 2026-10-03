<?php

namespace App\Models;

use App\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Eine Rundnachricht: als Entwurf gespeichert oder verschickt (Protokoll). */
class Rundnachricht extends Model
{
    use BelongsToTenant;

    protected $table = 'rundnachrichten';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['user_ids' => 'array', 'bloecke' => 'array', 'kanaele' => 'array', 'mail_alle' => 'boolean', 'chat' => 'boolean', 'persoenlich' => 'boolean', 'sent_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    /** Felder, wie das Formular der Rundnachricht sie erwartet. */
    public function formular(): array
    {
        return [
            'an' => $this->an, 'program_id' => $this->program_id, 'user_ids' => $this->user_ids ?? [],
            'titel' => $this->titel, 'text' => $this->text, 'url' => $this->url, 'bloecke' => $this->bloecke ?? [],
            'kanaele' => $this->kanaele ?? ['push', 'mail'], 'mail_alle' => $this->mail_alle, 'chat' => $this->chat, 'persoenlich' => $this->persoenlich,
        ];
    }

    /** Formularwerte in Felder. */
    public static function ausFormular(array $data): array
    {
        return [
            'an' => $data['an'] ?? 'alle', 'program_id' => $data['program_id'] ?? null, 'user_ids' => array_values(array_map('intval', (array) ($data['user_ids'] ?? []))) ?: null,
            'titel' => filled($data['titel'] ?? null) ? trim($data['titel']) : null, 'text' => trim((string) ($data['text'] ?? '')), 'url' => filled($data['url'] ?? null) ? $data['url'] : null, 'bloecke' => self::bloecke($data),
            'kanaele' => array_values((array) ($data['kanaele'] ?? ['push', 'mail'])), 'mail_alle' => (bool) ($data['mail_alle'] ?? false), 'chat' => (bool) ($data['chat'] ?? false), 'persoenlich' => (bool) ($data['persoenlich'] ?? false),
        ];
    }

    /** Bausteine aus dem Formular, ohne leere Zeilen; null, wenn keine da sind (dann zeigt die Mail den Text). */
    public static function bloecke(array $data): ?array
    {
        $b = array_values(array_filter((array) ($data['bloecke'] ?? []), fn ($b) => is_array($b) && ! empty($b['type'])));

        return $b ?: null;
    }

    public function wohin(): string
    {
        return match ($this->an) {
            'programm' => $this->program?->title ?? 'Kurs',
            'begleitung' => '1:1 Begleitung',
            'einzelne' => count($this->user_ids ?? []).' Personen',
            default => 'Alle',
        };
    }
}

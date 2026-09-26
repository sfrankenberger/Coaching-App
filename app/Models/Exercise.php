<?php

namespace App\Models;

use App\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Ein Teil einer Uebung: Frage, Skala, Werteliste, Haken, Notizfeld,
 * oder ein reiner Text dazwischen (heading, hint).
 */
class Exercise extends Model
{
    use BelongsToTenant;

    public const TYPES = [
        'text' => 'Frage mit Textfeld',
        'note' => 'Grosses Notizfeld',
        'scale' => 'Skala 1 bis 10',
        'values' => 'Werte zum Anklicken',
        'choice' => 'Auswahl',
        'checkbox' => 'Zum Abhaken',
        'heading' => 'Zwischentitel',
        'hint' => 'Hinweistext',
        'list' => 'Liste, Zeile für Zeile',
        'pairs' => 'Zwei Spalten (Gedanke und Umkehrung)',
        'letter' => 'Brief, grosses Feld',
        'mirror' => 'Spiegel: zeigt eine frühere Antwort',
        'audio' => 'Aufnahme: eigenen Text einsprechen',
        'takeaway' => 'Mitnehmen: Antworten kopieren oder drucken',
        'practice' => 'Tägliche Praxis mit Tageszähler',
        'wheel' => 'Lebensrad aus den Skalen der Einheit',
    ];

    public const ANSWERABLE = ['text', 'note', 'scale', 'values', 'choice', 'checkbox', 'list', 'pairs', 'letter', 'audio'];

    /** Teile, deren Antwort Text ist (fuer Spiegel und Mitnehmen). */
    public const TEXTLIKE = ['text', 'note', 'list', 'pairs', 'letter'];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'settings' => 'array',
        ];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(Answer::class);
    }

    public function isAnswerable(): bool
    {
        return in_array($this->type, self::ANSWERABLE, true);
    }

    /** Antwortwert als lesbarer Text (Liste zeilenweise, Paare mit Pfeil). */
    public static function alsText(mixed $v): string
    {
        if (! is_array($v)) {
            return trim((string) $v);
        }

        return collect($v)->map(fn ($z) => is_array($z) ? trim(implode(' → ', array_filter(array_map('trim', array_map('strval', $z))))) : trim((string) $z))
            ->filter()->join("\n");
    }
}

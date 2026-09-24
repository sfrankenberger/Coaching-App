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
    ];

    public const ANSWERABLE = ['text', 'note', 'scale', 'values', 'choice', 'checkbox'];

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
}

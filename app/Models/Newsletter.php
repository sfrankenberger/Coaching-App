<?php

namespace App\Models;

use App\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Ein Newsletter: Vorlage (Bild, Headline, Text, Knopf), Empfaenger nach Tags, Versand in Wellen, Zahlen. */
class Newsletter extends Model
{
    use BelongsToTenant;

    public const STATUS = ['entwurf' => 'Entwurf', 'geplant' => 'geplant', 'laeuft' => 'wird verschickt', 'gesendet' => 'gesendet'];

    protected $table = 'newsletter';

    protected $guarded = [];

    protected $attributes = ['status' => 'entwurf'];

    protected function casts(): array
    {
        return ['tags' => 'array', 'settings' => 'array', 'geplant_at' => 'datetime', 'gestartet_at' => 'datetime', 'gesendet_at' => 'datetime'];
    }

    public function versand(): HasMany
    {
        return $this->hasMany(NewsletterVersand::class);
    }

    public function autor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function istEntwurf(): bool
    {
        return in_array($this->status, ['entwurf', 'geplant'], true);
    }

    public function empfaengerQuery()
    {
        return Kontakt::query()->bestaetigt()->mitTags($this->tags ?? []);
    }
}

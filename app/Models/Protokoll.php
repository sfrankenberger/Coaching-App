<?php

namespace App\Models;

use App\Tenancy\CurrentTenant;
use App\Tenancy\TenantScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use Spatie\Activitylog\Models\Activity;

/**
 * Ein Eintrag im Aenderungsprotokoll: wer hat wann was an welchem Datensatz geaendert.
 *
 * Nutzt bewusst nicht BelongsToTenant: tenant_id darf leer sein (Aenderungen an Mandanten
 * und Personen selbst), der Mandanten-Scope gilt trotzdem. Die Plattform-Verwaltung liest
 * ohne Scope (withoutGlobalScope(TenantScope::class)).
 */
class Protokoll extends Activity
{
    public const MASKE = '***';

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function (Protokoll $p) {
            $p->tenant_id ??= app(CurrentTenant::class)->id();
        });
    }

    public function scopeFuerPerson(Builder $query, User|int $person): Builder
    {
        return $query->where('person_id', $person instanceof User ? $person->id : $person);
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(User::class, 'person_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** Name der Person, die die Aenderung gemacht hat, sonst "System". */
    public function wer(): string
    {
        if ($this->causer_type && $this->causer_id) {
            return $this->causer?->name ?? $this->getProperty('verursacher') ?? 'gelöschte Person';
        }

        return match ($this->getProperty('quelle')) {
            'konsole' => 'System (Konsole)',
            'queue' => 'System (Hintergrund)',
            default => 'System',
        };
    }

    /** Lesbarer Typ des Datensatzes, z. B. "Programm". */
    public function typ(): string
    {
        if (! $this->subject_type) {
            return '';
        }
        $klasse = Relation::getMorphedModel($this->subject_type) ?? $this->subject_type;

        return config('protokoll.typen')[$klasse] ?? class_basename($klasse);
    }

    /** Titel des Datensatzes zum Zeitpunkt der Aenderung. */
    public function titel(): string
    {
        return (string) ($this->getProperty('titel') ?? ($this->subject_id ? '#'.$this->subject_id : ''));
    }

    /** Lesbares Ereignis, z. B. "geaendert". */
    public function ereignis(): string
    {
        return config('protokoll.ereignisse')[$this->event] ?? $this->description;
    }

    /** Ein Satz fuer Listen: "Programm «Frühling» geändert". */
    public function satz(): string
    {
        $titel = $this->titel();

        return trim($this->typ().($titel !== '' ? ' «'.$titel.'»' : '').' '.$this->ereignis());
    }

    /**
     * Geaenderte Felder als Zeilen fuer die Anzeige.
     *
     * @return array<int, array{feld: string, alt: mixed, neu: mixed, maskiert: bool}>
     */
    public function aenderungen(): array
    {
        $neu = (array) ($this->attribute_changes?->get('attributes') ?? []);
        $alt = (array) ($this->attribute_changes?->get('old') ?? []);
        $zeilen = [];

        foreach (array_unique(array_merge(array_keys($alt), array_keys($neu))) as $feld) {
            $a = $alt[$feld] ?? null;
            $n = $neu[$feld] ?? null;
            $zeilen[] = ['feld' => $feld, 'alt' => $a, 'neu' => $n, 'maskiert' => $a === self::MASKE || $n === self::MASKE];
        }

        return $zeilen;
    }
}

<?php

namespace App\Support\Papierkorb;

use App\Models;
use App\Models\Protokoll;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\CurrentTenant;
use App\Tenancy\TenantScope;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/** Der Papierkorb ueber alle Modelle: auflisten, zurueckholen, endgueltig loeschen, leeren. */
class Papierkorb
{
    /** Modelle mit Papierkorb (Trait ImPapierkorb). Reihenfolge = Anzeige im Filter. */
    public const MODELLE = [
        Models\Membership::class, Models\Program::class, Models\ProgramStep::class, Models\Unit::class, Models\Exercise::class,
        Models\Question::class, Models\Comment::class, Models\Event::class, Models\Task::class, Models\Note::class,
        Models\CoachNote::class, Models\Reflection::class, Models\JournalEntry::class, Models\Resource::class,
        Models\Offer::class, Models\Entitlement::class, Models\Booking::class, Models\BookingType::class,
        Models\Post::class, Models\PodcastEpisode::class, Models\Topic::class, Models\Tool::class, Models\Wissen::class, Models\Sammlung::class,
    ];

    public const FRIST_TAGE = 90;

    /** Lesbare Namen je Morph-Alias, fuer den Filter. */
    public function typen(): array
    {
        return collect(self::MODELLE)->mapWithKeys(fn ($k) => [Relation::getMorphAlias($k) => config('protokoll.typen')[$k] ?? class_basename($k)])->all();
    }

    /**
     * Eintraege im Papierkorb, neueste zuerst. Kinder, deren Elternteil selbst im Papierkorb liegt, bleiben weg.
     *
     * @return Collection<int, array{typ: string, typName: string, id: int, titel: string, geloeschtAm: Carbon, person: ?string, von: ?string, mandant: ?string, endgueltigAm: Carbon}>
     */
    public function eintraege(bool $plattform = false, ?string $typ = null, int $limit = 300): Collection
    {
        $zeilen = collect();
        foreach (self::MODELLE as $klasse) {
            $alias = Relation::getMorphAlias($klasse);
            if ($typ && $typ !== $alias) {
                continue;
            }
            $q = $klasse::onlyTrashed()->latest('deleted_at')->limit($limit);
            if ($plattform) {
                $q->withoutGlobalScope(TenantScope::class);
            }
            foreach ($q->get() as $m) {
                if ($m->papierkorbMitElternteil()) {
                    continue;
                }
                $zeilen->push([
                    'typ' => $alias,
                    'typName' => config('protokoll.typen')[$klasse] ?? class_basename($klasse),
                    'id' => $m->getKey(),
                    'titel' => method_exists($m, 'protokollPrivat') && $m->protokollPrivat() ? '(privater Eintrag)' : $m->protokollTitel(),
                    'geloeschtAm' => $m->deleted_at,
                    'endgueltigAm' => $m->deleted_at->copy()->addDays(self::FRIST_TAGE),
                    'person' => $m instanceof Models\Membership ? null : ($m->getAttribute('user_id') ? $m->user()->withoutGlobalScopes()->value('name') : null),
                    'von' => null,
                    'mandant' => $plattform ? $m->getAttribute('tenant_id') : null,
                ]);
            }
        }

        $zeilen = $zeilen->sortByDesc(fn ($z) => $z['geloeschtAm']->getTimestamp())->take($limit)->values();

        return $this->mitVerursacher($zeilen, $plattform)->map(function ($z) use ($plattform) {
            if ($plattform) {
                $z['mandant'] = $z['mandant'] ? (Tenant::find($z['mandant'])?->name ?? '#'.$z['mandant']) : null;
            }

            return $z;
        });
    }

    /** Wer hat geloescht: aus dem Aenderungsprotokoll, je Typ eine Abfrage. */
    protected function mitVerursacher(Collection $zeilen, bool $plattform): Collection
    {
        $namen = [];
        foreach ($zeilen->groupBy('typ') as $typ => $gruppe) {
            $q = Protokoll::query()->where('subject_type', $typ)->whereIn('subject_id', $gruppe->pluck('id'))->where('event', 'deleted')->with('causer')->latest('id');
            if ($plattform) {
                $q->withoutGlobalScope(TenantScope::class);
            }
            foreach ($q->get() as $e) {
                $namen[$typ.':'.$e->subject_id] ??= $e->wer();
            }
        }

        return $zeilen->map(function ($z) use ($namen) {
            $z['von'] = $namen[$z['typ'].':'.$z['id']] ?? null;

            return $z;
        });
    }

    /** Einen Eintrag im Papierkorb finden (mit Mandanten-Scope, in der Plattform ohne). */
    public function finden(string $typ, int $id, bool $plattform = false): ?Model
    {
        $klasse = Relation::getMorphedModel($typ);
        if (! $klasse || ! in_array($klasse, self::MODELLE, true)) {
            return null;
        }
        $q = $klasse::onlyTrashed()->whereKey($id);
        if ($plattform) {
            $q->withoutGlobalScope(TenantScope::class);
        }

        return $q->first();
    }

    /** Zurueckholen, im Mandanten des Eintrags (Kinder brauchen den Scope). */
    public function wiederherstellen(Model $m): void
    {
        $this->imMandanten($m, fn () => $m->restore());
    }

    /** Endgueltig loeschen, mit allem, was dranhaengt. Nur Plattform. */
    public function endgueltig(Model $m): void
    {
        $this->imMandanten($m, fn () => $m->forceDelete());
    }

    /** Alles, was laenger als die Frist im Papierkorb liegt, endgueltig loeschen. Liefert die Anzahl. */
    public function leeren(int $tage = self::FRIST_TAGE, bool $trocken = false): int
    {
        $grenze = now()->subDays($tage);
        $anzahl = 0;
        foreach (self::MODELLE as $klasse) {
            $alte = $klasse::onlyTrashed()->withoutGlobalScope(TenantScope::class)->where('deleted_at', '<', $grenze)->get();
            foreach ($alte as $m) {
                if (! $trocken) {
                    // Kinder sind eventuell schon ueber das Elternteil weg
                    $this->imMandanten($m, fn () => $m->fresh() ? $m->forceDelete() : null);
                }
                $anzahl++;
            }
        }
        if (! $trocken && $anzahl) {
            Log::info("Papierkorb geleert: {$anzahl} Eintraege aelter als {$tage} Tage endgueltig geloescht.");
        }

        return $anzahl;
    }

    /**
     * Eine Person samt allem, was ihr gehoert, endgueltig loeschen (Plattform, nach Bestaetigung).
     * Die Datenbank raeumt ueber die Fremdschluessel auf, Mitgliedschaften und Eintraege im Papierkorb eingeschlossen.
     */
    public function personLoeschen(User $person, User $von): void
    {
        if ($person->is_platform_admin) {
            throw new \RuntimeException('Plattform-Admins lassen sich hier nicht loeschen.');
        }
        Log::channel('backup-restore')->warning('Person endgueltig geloescht', ['user_id' => $person->id, 'email' => $person->email, 'von' => $von->email]);

        $person->avatarLoeschen();
        $person->tokens()->delete();
        $person->delete();
    }

    protected function imMandanten(Model $m, callable $fn): mixed
    {
        $tenantId = $m->getAttribute('tenant_id');
        $tenant = $tenantId ? Tenant::find($tenantId) : null;

        return $tenant ? app(CurrentTenant::class)->run($tenant, $fn) : $fn();
    }
}

<?php

namespace App\Shop;

use App\Models\Membership;
use App\Models\User;
use App\Models\Verkauf;
use Illuminate\Support\Collection;

/**
 * Zahlen fuer die Inhaberin (wie lea-zahlen.php): Umsatz Jahr, Monat, offen, Jahre, zwoelf Monate,
 * Angebote, beste Kundinnen, dazu Kennzahlen je Person fuer das Dossier. Grundlage ist die
 * Tabelle verkaeufe (alles, was ueber die App verkauft oder in Rechnung gestellt wurde);
 * stornierte Verkaeufe zaehlen nicht. Summen je Waehrung, damit CHF und EUR nicht vermischt werden.
 */
class Zahlen
{
    public const BESTE = 10;

    protected function gueltig()
    {
        return Verkauf::query()->where('status', '!=', 'storniert');
    }

    /** Summen je Waehrung: ['CHF' => 1234.5, ...]. */
    protected function summen($query): array
    {
        return $query->get(['betrag', 'waehrung'])->groupBy('waehrung')->map(fn ($g) => round((float) $g->sum('betrag'), 2))->sortKeys()->all();
    }

    public function uebersicht(): array
    {
        $jetzt = now();
        $jahre = $this->gueltig()->get(['betrag', 'waehrung', 'created_at'])
            ->groupBy(fn ($v) => $v->created_at->format('Y'))
            ->map(fn ($g) => $g->groupBy('waehrung')->map(fn ($w) => round((float) $w->sum('betrag'), 2))->sortKeys()->all())
            ->sortKeysDesc();

        $monate = collect(range(11, 0))->map(function ($zurueck) use ($jetzt) {
            $m = $jetzt->copy()->startOfMonth()->subMonths($zurueck);

            return [
                'monat' => $m,
                'label' => $m->translatedFormat('M Y'),
                'summen' => $this->summen($this->gueltig()->whereBetween('created_at', [$m->copy()->utc(), $m->copy()->endOfMonth()->utc()])),
                'anzahl' => $this->gueltig()->whereBetween('created_at', [$m->copy()->utc(), $m->copy()->endOfMonth()->utc()])->count(),
            ];
        });

        $angebote = $this->gueltig()->get(['title', 'offer_id', 'betrag', 'waehrung'])
            ->groupBy(fn ($v) => $v->offer_id ?: $v->title)
            ->map(fn ($g) => ['titel' => $g->first()->title, 'anzahl' => $g->count(), 'summen' => $g->groupBy('waehrung')->map(fn ($w) => round((float) $w->sum('betrag'), 2))->sortKeys()->all(), 'sort' => (float) $g->sum('betrag')])
            ->sortByDesc('sort')->values();

        return [
            'jahr' => $this->summen($this->gueltig()->where('created_at', '>=', $jetzt->copy()->startOfYear()->utc())),
            'monat' => $this->summen($this->gueltig()->where('created_at', '>=', $jetzt->copy()->startOfMonth()->utc())),
            'offen' => $this->summen(Verkauf::query()->where('status', 'offen')),
            'offen_anzahl' => Verkauf::query()->where('status', 'offen')->count(),
            'ueberfaellig' => Verkauf::query()->where('status', 'offen')->whereNotNull('faellig_am')->where('faellig_am', '<', $jetzt->toDateString())->count(),
            'jahre' => $jahre,
            'monate' => $monate,
            'angebote' => $angebote,
            'beste' => $this->beste(),
            'gesamt' => $this->summen($this->gueltig()),
            'verkaeufe' => $this->gueltig()->count(),
        ];
    }

    /** Die besten Kundinnen: Umsatz gesamt je Person, mit Rang. */
    public function beste(int $limit = self::BESTE): Collection
    {
        return $this->rangliste()->take($limit);
    }

    protected function rangliste(): Collection
    {
        return $this->gueltig()->with('user:id,name,email')->get(['user_id', 'betrag', 'waehrung', 'created_at', 'title'])
            ->groupBy('user_id')
            ->map(fn ($g) => [
                'user' => $g->first()->user,
                'anzahl' => $g->count(),
                'summen' => $g->groupBy('waehrung')->map(fn ($w) => round((float) $w->sum('betrag'), 2))->sortKeys()->all(),
                'sort' => (float) $g->sum('betrag'),
                'letzte' => $g->max('created_at'),
            ])
            ->filter(fn ($z) => $z['user'])
            ->sortByDesc('sort')->values()
            ->map(function ($z, $i) {
                $z['rang'] = $i + 1;

                return $z;
            });
    }

    /** Kennzahlen einer Person fuer das Dossier: Umsatz, Anzahl, offen, Rang und Anteil, letzte Rechnung, dabei seit, wofuer, Hinweis. */
    public function person(User $user): array
    {
        $alle = $this->gueltig()->where('user_id', $user->id)->latest()->get();
        $rangliste = $this->rangliste();
        $eintrag = $rangliste->firstWhere('user.id', $user->id);
        $gesamtAlle = (float) $rangliste->sum('sort');
        $offen = $alle->where('status', 'offen');
        $membership = Membership::where('user_id', $user->id)->first();
        $dabeiSeit = $membership?->joined_at ?? $alle->min('created_at');
        $summen = $alle->groupBy('waehrung')->map(fn ($w) => round((float) $w->sum('betrag'), 2))->sortKeys()->all();

        $hinweis = match (true) {
            $alle->isEmpty() => 'Noch kein Verkauf über die App. Ältere Rechnungen stehen in der Buchhaltung.',
            $offen->isNotEmpty() && $offen->contains(fn ($v) => $v->faellig_am && $v->faellig_am->isPast()) => 'Eine Rechnung ist überfällig. Ein freundlicher Hinweis im Gespräch wirkt oft mehr als eine Mahnung.',
            $offen->isNotEmpty() => 'Eine Rechnung ist noch offen, aber nicht fällig. Nichts zu tun.',
            $eintrag && $eintrag['rang'] <= 3 => 'Eine der treuesten Kundinnen. Ein persönliches Dankeschön passt hier gut.',
            $alle->first()->created_at->lt(now()->subMonths(6)) => 'Der letzte Kauf liegt über ein halbes Jahr zurück. Vielleicht passt ein Angebot oder ein kurzes Hallo.',
            default => 'Alles bezahlt, zuletzt vor Kurzem. Einfach weiter begleiten.',
        };

        return [
            'summen' => $summen,
            'anzahl' => $alle->count(),
            'offen' => $offen->groupBy('waehrung')->map(fn ($w) => round((float) $w->sum('betrag'), 2))->sortKeys()->all(),
            'offen_anzahl' => $offen->count(),
            'rang' => $eintrag['rang'] ?? null,
            'von' => $rangliste->count(),
            'anteil' => $eintrag && $gesamtAlle > 0 ? round($eintrag['sort'] / $gesamtAlle * 100, 1) : null,
            'letzte' => $alle->first(),
            'dabei_seit' => $dabeiSeit,
            'wofuer' => $alle->pluck('title')->unique()->values()->all(),
            'hinweis' => $hinweis,
        ];
    }

    public static function geld(array $summen): string
    {
        if ($summen === []) {
            return '0.00';
        }

        return collect($summen)->map(fn ($s, $w) => number_format((float) $s, 2, '.', "'").' '.$w)->join(', ');
    }
}

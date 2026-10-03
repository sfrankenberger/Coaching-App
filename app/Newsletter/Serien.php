<?php

namespace App\Newsletter;

use App\Mail\NewsletterMail;
use App\Auth\MagicLink;
use App\Models\Booking;
use App\Models\Kontakt;
use App\Models\Newsletter;
use App\Models\Program;
use App\Models\Serie;
use App\Models\SerienLauf;
use App\Programs\ProgressTracker;
use App\Tenancy\CurrentTenant;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Serien (Autoresponder): Tag loest aus, Schritte mit Abstand in Tagen, Schritt 0 Tage sofort.
 * Ein Schritt kann eine Bedingung haben (nur wenn die Person noch nie in der App war, den Kurs nicht begonnen
 * oder nicht beendet hat, kein Klarheitsgespraech gebucht hat); trifft sie nicht zu, wird der Schritt uebersprungen.
 * Im Text steht {anmeldelink} fuer einen frischen Einstiegslink (7 Tage), wenn der Kontakt ein Konto hat.
 */
class Serien
{
    public const BEDINGUNGEN = [
        '' => 'Immer',
        'nicht_angemeldet' => 'Nur wenn die Person noch nie in der App war',
        'kurs_nicht_begonnen' => 'Nur wenn der Kurs der Serie noch nicht begonnen ist',
        'kurs_nicht_fertig' => 'Nur wenn der Kurs der Serie noch nicht fertig ist',
        'kurs_fertig' => 'Nur wenn der Kurs der Serie fertig ist',
        'kein_gespraech' => 'Nur wenn noch kein Klarheitsgespräch gebucht wurde',
    ];

    public function __construct(protected CurrentTenant $current) {}

    /** Trifft die Bedingung des Schritts fuer diesen Kontakt zu? Ohne Konto gelten nur "immer" und "nicht angemeldet". */
    public function bedingungErfuellt(Serie $serie, array $schritt, Kontakt $k): bool
    {
        $b = (string) ($schritt['bedingung'] ?? '');
        if ($b === '') {
            return true;
        }
        $user = $k->user;
        $program = ($pid = (int) data_get($serie->settings, 'program_id')) ? Program::find($pid) : null;

        return match ($b) {
            'nicht_angemeldet' => ! $user || $user->membershipIn()?->last_seen_at === null,
            'kurs_nicht_begonnen' => $user && $program ? app(ProgressTracker::class)->completedUnitIds($user, $program)->isEmpty() : true,
            'kurs_nicht_fertig' => $user && $program ? ($s = app(ProgressTracker::class)->summary($user, $program))['done'] < $s['total'] : true,
            'kurs_fertig' => $user && $program ? ($s = app(ProgressTracker::class)->summary($user, $program))['total'] > 0 && $s['done'] >= $s['total'] : false,
            'kein_gespraech' => ! $user || ! Booking::where('user_id', $user->id)->whereHas('type', fn ($q) => $q->where('key', 'erst'))->where('status', 'gebucht')->exists(),
            default => true,
        };
    }

    /** {anmeldelink} im Schritt ersetzen: Einstiegslink fuer das Konto, sonst die Anmeldeseite. */
    protected function mitAnmeldelink(array $schritt, Kontakt $k): array
    {
        $json = json_encode($schritt, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (! str_contains($json, '{anmeldelink}')) {
            return $schritt;
        }
        $link = $k->user ? app(MagicLink::class)->create($k->user, route('home', absolute: false), null, 60 * 24 * 7) : route('anmelden');

        return json_decode(str_replace('{anmeldelink}', $link, $json), true) ?: $schritt;
    }

    /** Kontakt bekommt einen Tag: passende aktive Serien starten (einmal je Kontakt und Serie). */
    public function ausloesen(Kontakt $k, string $tag): int
    {
        $n = 0;
        foreach (Serie::where('aktiv', true)->where('tag', Kontakt::tagSauber($tag))->get() as $serie) {
            if (empty($serie->schritte) || SerienLauf::where('serie_id', $serie->id)->where('kontakt_id', $k->id)->exists()) {
                continue;
            }
            $lauf = SerienLauf::create(['serie_id' => $serie->id, 'kontakt_id' => $k->id, 'schritt' => 0, 'naechste_at' => now()->addDays((int) ($serie->schritt(0)['tage'] ?? 0))]);
            $n++;
            if ((int) ($serie->schritt(0)['tage'] ?? 0) === 0) {
                $this->schrittSenden($lauf);
            }
        }

        return $n;
    }

    /** Alle faelligen Schritte verschicken. */
    public function lauf(): int
    {
        $n = 0;
        foreach (SerienLauf::whereNull('fertig_at')->whereNotNull('naechste_at')->where('naechste_at', '<=', now()->utc())->with(['serie', 'kontakt'])->get() as $lauf) {
            $n += $this->schrittSenden($lauf) ? 1 : 0;
        }

        return $n;
    }

    protected function schrittSenden(SerienLauf $lauf): bool
    {
        $serie = $lauf->serie;
        $k = $lauf->kontakt;
        $schritt = $serie?->schritt($lauf->schritt);
        if (! $serie || ! $k || ! $schritt) {
            $lauf->forceFill(['fertig_at' => now()])->save();

            return false;
        }
        if (! $serie->aktiv || ! $k->istBestaetigt() || ! $k->hatTag($serie->tag)) {
            $lauf->forceFill(['fertig_at' => now()])->save();   // abgemeldet, Tag weg oder Serie aus: still beenden

            return false;
        }
        if (! $this->bedingungErfuellt($serie, $schritt, $k)) {
            $this->weiter($lauf, $serie);   // Bedingung trifft nicht zu: Schritt ueberspringen, naechsten einplanen

            return false;
        }
        $schritt = $this->mitAnmeldelink($schritt, $k);
        $mail = new Newsletter(['betreff' => $schritt['betreff'] ?? $serie->titel, 'vorschautext' => $schritt['vorschautext'] ?? null, 'titel' => $schritt['titel'] ?? null, 'text' => $schritt['text'] ?? '', 'bild_url' => $schritt['bild_url'] ?? null, 'knopf_text' => $schritt['knopf_text'] ?? null, 'knopf_url' => $schritt['knopf_url'] ?? null, 'bloecke' => $schritt['bloecke'] ?? null]);
        try {
            Mail::to($k->email, $k->name)->send(new NewsletterMail($mail, $k, null));
        } catch (\Throwable $e) {
            Log::warning('Serienmail nicht gesendet', ['serie' => $serie->id, 'kontakt' => $k->id, 'fehler' => $e->getMessage()]);
        }
        $this->weiter($lauf, $serie);

        return true;
    }

    /** Zum naechsten Schritt: Abstand ab jetzt, am Ende fertig. */
    protected function weiter(SerienLauf $lauf, Serie $serie): void
    {
        $naechster = $serie->schritt($lauf->schritt + 1);
        $lauf->forceFill([
            'schritt' => $lauf->schritt + 1,
            'naechste_at' => $naechster ? now()->addDays(max(0, (int) ($naechster['tage'] ?? 1))) : null,
            'fertig_at' => $naechster ? null : now(),
        ])->save();
    }
}

<?php

namespace App\Newsletter;

use App\Mail\NewsletterMail;
use App\Models\Kontakt;
use App\Models\Newsletter;
use App\Models\Serie;
use App\Models\SerienLauf;
use App\Tenancy\CurrentTenant;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/** Serien (Autoresponder): Tag loest aus, Schritte mit Abstand in Tagen, Schritt 0 Tage sofort. */
class Serien
{
    public function __construct(protected CurrentTenant $current) {}

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
        $mail = new Newsletter(['betreff' => $schritt['betreff'] ?? $serie->titel, 'vorschautext' => $schritt['vorschautext'] ?? null, 'titel' => $schritt['titel'] ?? null, 'text' => $schritt['text'] ?? '', 'bild_url' => $schritt['bild_url'] ?? null, 'knopf_text' => $schritt['knopf_text'] ?? null, 'knopf_url' => $schritt['knopf_url'] ?? null]);
        try {
            Mail::to($k->email, $k->name)->send(new NewsletterMail($mail, $k, null));
        } catch (\Throwable $e) {
            Log::warning('Serienmail nicht gesendet', ['serie' => $serie->id, 'kontakt' => $k->id, 'fehler' => $e->getMessage()]);
        }
        $naechster = $serie->schritt($lauf->schritt + 1);
        $lauf->forceFill([
            'schritt' => $lauf->schritt + 1,
            'naechste_at' => $naechster ? now()->addDays(max(0, (int) ($naechster['tage'] ?? 1))) : null,
            'fertig_at' => $naechster ? null : now(),
        ])->save();

        return true;
    }
}

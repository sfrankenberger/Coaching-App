<?php

namespace App\Shop;

use App\Auth\MagicLink;
use App\Mail\RechnungMail;
use App\Models\CoachNote;
use App\Models\Offer;
use App\Models\User;
use App\Models\Verkauf;
use App\Programs\ProgramAccess;
use App\Tenancy\CurrentTenant;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

/**
 * Ein Verkauf aus dem Dossier (spaeter auch aus der Kasse): Zugang geben, Verkauf festhalten,
 * Rechnung in der Buchhaltung anlegen, Rechnung per Mail schicken. Bei Kauf auf Rechnung
 * entscheidet die Vorgabe des Mandanten (buchhaltung.zugang_bei_rechnung: sofort | bezahlt),
 * ob der Zugang gleich gilt oder erst nach dem Zahlungseingang.
 */
class Verkaufen
{
    public function __construct(protected Zugang $zugang, protected ProgramAccess $access, protected CurrentTenant $current) {}

    /**
     * $daten: betrag (float), waehrung, zahlungsart (rechnung|bezahlt|kostenlos), rechnung (bool), mail (bool),
     * tage (?int), sitzungen (?int), notiz (?string), herkunft (?string).
     */
    public function verkaufen(?User $verkaeufer, User $user, Offer $offer, array $daten): Verkauf
    {
        $tenant = $this->current->getOrFail();
        $betrag = round((float) ($daten['betrag'] ?? 0), 2);
        $art = $betrag > 0 ? (string) ($daten['zahlungsart'] ?? 'rechnung') : 'kostenlos';
        $b = Buchhaltung::fuer($tenant);
        $rechnung = $betrag > 0 && ($daten['rechnung'] ?? false) && $b && $b->kannSchreiben();
        $wartenAufZahlung = $art === 'rechnung' && $rechnung && $tenant->setting('buchhaltung.zugang_bei_rechnung', 'sofort') === 'bezahlt';

        $ende = filled($daten['tage'] ?? null) ? now()->addDays((int) $daten['tage']) : ($offer->access_days ? now()->addDays((int) $offer->access_days) : null);
        $ref = 'verkauf-'.now()->format('YmdHis').'-'.$user->id;
        $e = $this->zugang->grant($user, $offer, 'manual', $ref, now(), $ende, ! $wartenAufZahlung && ! ($daten['mail'] ?? false));
        if ($wartenAufZahlung) {
            $e->forceFill(['status' => 'pending'])->save();   // Programme kommen erst mit dem Zahlungseingang dazu
        } else {
            $this->programmeGeben($user, $offer, (int) ($daten['sitzungen'] ?? 0));
        }
        if (($m = $user->membershipIn($tenant)) && $m->role->value === 'guest') {
            $m->forceFill(['role' => 'member'])->save();
        }

        $v = Verkauf::create([
            'user_id' => $user->id, 'offer_id' => $offer->id, 'entitlement_id' => $e->id, 'created_by' => $verkaeufer?->id,
            'title' => $offer->title, 'betrag' => $betrag, 'waehrung' => strtoupper((string) ($daten['waehrung'] ?? $tenant->currency ?? 'CHF')),
            'zahlungsart' => $art, 'status' => $art === 'rechnung' ? 'offen' : 'bezahlt', 'bezahlt_am' => $art === 'rechnung' ? null : now(),
            'herkunft' => $daten['herkunft'] ?? 'dossier', 'notiz' => filled($daten['notiz'] ?? null) ? trim($daten['notiz']) : null,
            'settings' => ['sitzungen' => (int) ($daten['sitzungen'] ?? 0), 'warten_auf_zahlung' => $wartenAufZahlung],
        ]);

        $fehler = null;
        if ($rechnung) {
            try {
                $r = $b->rechnungAnlegen($user, $offer->title, [['text' => $offer->title, 'betrag' => $betrag, 'anzahl' => 1]], $v->waehrung, $art === 'bezahlt', 'app-verkauf-'.$v->id);
                $v->forceFill(['rechnung_id' => (string) $r['id'], 'rechnung_nr' => $r['nr'] ?: null, 'rechnung_link' => $r['link'] ?: null, 'faellig_am' => $art === 'rechnung' ? $r['faellig'] : null])->save();
            } catch (RuntimeException $ex) {
                report($ex);
                $fehler = $ex->getMessage();
                $v->forceFill(['settings' => array_merge($v->settings ?? [], ['rechnung_fehler' => $fehler])])->save();
            }
        }

        if ($daten['mail'] ?? false) {
            $this->mailen($v, $b, ! $wartenAufZahlung);
        }

        if ($verkaeufer) {
            $zeilen = array_filter([
                'Verkauft: '.$offer->title.($betrag > 0 ? ', '.$v->betragText().' ('.(Verkauf::ZAHLUNGSARTEN[$art] ?? $art).')' : ' (kostenlos)'),
                $v->rechnung_nr ? 'Rechnung '.$v->rechnung_nr.($v->faellig_am ? ', fällig '.$v->faellig_am->translatedFormat('j. F Y') : '') : null,
                $fehler ? 'Rechnung nicht angelegt: '.$fehler : null,
                $wartenAufZahlung ? 'Zugang gilt ab Zahlungseingang' : null,
                ! empty($daten['sitzungen']) ? 'inkl. '.$daten['sitzungen'].' 1:1-Sitzungen' : null,
                $ende ? 'bis '.$ende->translatedFormat('j. F Y') : null,
                $v->notiz,
            ]);
            CoachNote::create(['user_id' => $user->id, 'author_id' => $verkaeufer->id, 'body' => implode("\n", $zeilen)]);
        }

        return $v;
    }

    /** In die Programme des Angebots aufnehmen, bei 1:1 mit zusaetzlichen Sitzungen. */
    protected function programmeGeben(User $user, Offer $offer, int $sitzungen): void
    {
        foreach ($offer->programs as $p) {
            $pm = $this->access->join($user, $p);
            if ($p->type === 'one_on_one' && $sitzungen > 0) {
                $pm->forceFill(['settings' => array_merge($pm->settings ?? [], ['sitzungen_extra' => (int) ($pm->settings['sitzungen_extra'] ?? 0) + $sitzungen])])->save();
            }
        }
    }

    /** Rechnung (mit PDF, wenn vorhanden) und Anmeldelink an die Person. */
    public function mailen(Verkauf $v, ?Buchhaltung $b, bool $mitZugang = true): void
    {
        $pdf = null;
        if ($v->rechnung_id && $b) {
            try {
                $pdf = $b->pdf((int) $v->rechnung_id);
            } catch (RuntimeException $ex) {
                Log::warning('Rechnungs-PDF nicht geladen: '.$ex->getMessage());
            }
        }
        $url = $mitZugang ? app(MagicLink::class)->create($v->user, route('home', absolute: false), null, 60 * 24 * 7) : null;
        Mail::to($v->user->email, $v->user->name)->send(new RechnungMail($v, $url, $pdf));
    }

    /** Zahlungseingang festhalten: Verkauf bezahlt, wartender Zugang freischalten und Bescheid geben. */
    public function bezahlt(Verkauf $v): void
    {
        if ($v->status === 'bezahlt') {
            return;
        }
        $v->forceFill(['status' => 'bezahlt', 'bezahlt_am' => now()])->save();
        $e = $v->entitlement;
        if ($e && $e->status === 'pending' && $v->offer) {
            $this->zugang->grant($v->user, $v->offer, $e->source, $e->source_ref, $e->starts_at ?? now(), $e->ends_at, true);
            $this->programmeGeben($v->user, $v->offer, (int) ($v->settings['sitzungen'] ?? 0));
        }
    }
}

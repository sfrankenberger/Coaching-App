<?php

namespace App\Shop;

use App\Auth\MagicLink;
use App\Chat\Chat;
use App\Mail\RechnungMail;
use App\Models\CoachNote;
use App\Models\Entitlement;
use App\Models\Offer;
use App\Models\Program;
use App\Models\User;
use App\Models\Verkauf;
use App\Notifications\Nachricht;
use App\Notifications\Notifier;
use App\Programs\ProgramAccess;
use App\Tenancy\CurrentTenant;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
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
        // Bei Stripe kommt die Rechnung (als Quittung) erst mit der Zahlung
        $rechnung = $betrag > 0 && $art !== 'stripe' && ($daten['rechnung'] ?? false) && $b && $b->kannSchreiben();
        $wartenAufZahlung = $art === 'stripe' || ($art === 'rechnung' && $rechnung && $tenant->setting('buchhaltung.zugang_bei_rechnung', 'sofort') === 'bezahlt');

        $ende = filled($daten['tage'] ?? null) ? now()->addDays((int) $daten['tage']) : ($offer->access_days ? now()->addDays((int) $offer->access_days) : null);
        $ref = 'verkauf-'.now()->format('YmdHis').'-'.$user->id;
        $e = $this->zugang->grant($user, $offer, 'manual', $ref, now(), $ende, ! $wartenAufZahlung && ! ($daten['mail'] ?? false));
        if ($wartenAufZahlung) {
            $e->forceFill(['status' => 'pending'])->save();   // Programme kommen erst mit dem Zahlungseingang dazu
        } else {
            $this->programmeGeben($user, $offer, (int) ($daten['sitzungen'] ?? 0), $e);
        }
        if (($m = $user->membershipIn($tenant)) && $m->role->value === 'guest') {
            $m->forceFill(['role' => 'member'])->save();
        }

        $v = Verkauf::create([
            'user_id' => $user->id, 'offer_id' => $offer->id, 'entitlement_id' => $e->id, 'created_by' => $verkaeufer?->id,
            'title' => $offer->title, 'betrag' => $betrag, 'waehrung' => strtoupper((string) ($daten['waehrung'] ?? $tenant->currency ?? 'CHF')),
            'zahlungsart' => $art, 'status' => in_array($art, ['rechnung', 'stripe'], true) ? 'offen' : 'bezahlt', 'bezahlt_am' => in_array($art, ['rechnung', 'stripe'], true) ? null : now(),
            'herkunft' => $daten['herkunft'] ?? 'dossier', 'notiz' => filled($daten['notiz'] ?? null) ? trim($daten['notiz']) : null,
            'settings' => ['sitzungen' => (int) ($daten['sitzungen'] ?? 0), 'warten_auf_zahlung' => $wartenAufZahlung, 'rechnung_gewuenscht' => (bool) ($daten['rechnung'] ?? false)] + (array) ($daten['settings'] ?? []),
        ]);
        // Waehrung der Person merken (wie im alten Bereich je Kundin)
        if ($m) {
            $m->forceFill(['settings' => array_merge($m->settings ?? [], ['waehrung' => $v->waehrung])])->saveQuietly();
        }

        $fehler = $rechnung ? $this->rechnungAnlegen($v, $b, $art === 'bezahlt') : null;

        if (($daten['mail'] ?? false) && $art !== 'stripe') {
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

    /** Rechnung in der Buchhaltung anlegen; bei Stoerung wird der Fehler am Verkauf vermerkt und spaeter nachgeholt. */
    public function rechnungAnlegen(Verkauf $v, ?Buchhaltung $b, bool $bezahlt): ?string
    {
        if (! $b || ! $b->kannSchreiben() || $v->rechnung_id || (float) $v->betrag <= 0) {
            return null;
        }
        try {
            $r = $b->rechnungAnlegen($v->user, $v->title, [['text' => $v->title, 'betrag' => (float) $v->betrag, 'anzahl' => 1]], $v->waehrung, $bezahlt, 'app-verkauf-'.$v->id);
            $s = $v->settings ?? [];
            unset($s['rechnung_fehler'], $s['rechnung_versuche']);
            $v->forceFill(['rechnung_id' => (string) $r['id'], 'rechnung_nr' => $r['nr'] ?: null, 'rechnung_link' => $r['link'] ?: null, 'faellig_am' => $bezahlt ? null : $r['faellig'], 'settings' => $s])->save();

            return null;
        } catch (RuntimeException $ex) {
            report($ex);
            $v->forceFill(['settings' => array_merge($v->settings ?? [], ['rechnung_fehler' => $ex->getMessage(), 'rechnung_versuche' => (int) ($v->settings['rechnung_versuche'] ?? 0) + 1])])->save();

            return $ex->getMessage();
        }
    }

    /**
     * Rechnung nachholen, wenn die Buchhaltung beim Verkauf gestoert war (wie E31): gelingt es, geht die
     * Mail mit PDF raus; nach drei Fehlversuchen bekommt das Team eine Warnung. Gibt true zurueck, wenn nachgeholt.
     */
    public function rechnungNachholen(Verkauf $v): bool
    {
        if ($v->rechnung_id || empty($v->settings['rechnung_fehler']) || $v->status === 'storniert') {
            return false;
        }
        $b = Buchhaltung::fuer($this->current->getOrFail());
        $fehler = $this->rechnungAnlegen($v, $b, $v->status === 'bezahlt');
        if ($fehler === null && $v->rechnung_id) {
            if (! empty($v->settings['mail_offen'])) {
                $this->mailen($v, $b, ! ($v->settings['warten_auf_zahlung'] ?? false));
                $v->forceFill(['settings' => array_merge($v->settings ?? [], ['mail_offen' => false])])->save();
            }

            return true;
        }
        if ((int) ($v->settings['rechnung_versuche'] ?? 0) === 3) {
            app(Notifier::class)->send(app(Chat::class)->teamIds(), new Nachricht(
                titel: 'Rechnung nicht angelegt: '.$v->user?->name,
                text: $v->title.', '.$v->betragText()."\n".($v->settings['rechnung_fehler'] ?? '')."\nDie App versucht es weiter, bitte in der Buchhaltung nachschauen.",
                url: ($mm = $v->user?->membershipIn()) ? route('coachees.show', $mm) : url('/coach/buchhaltung'), anlass: 'system', tag: 'rechnung-fehler-'.$v->id, mailImmer: true,
            ));
        }

        return false;
    }

    /** Zahlung ueber Stripe eingegangen: Zugang, Quittung in der Buchhaltung, Mail mit PDF und Anmeldelink, Team-Hinweis. */
    public function stripeBezahlt(Verkauf $v, array $session): void
    {
        if ($v->status === 'bezahlt') {
            return;
        }
        $v->forceFill(['settings' => array_merge($v->settings ?? [], ['stripe_payment_intent' => $session['payment_intent'] ?? null, 'stripe_session_id' => $session['id'] ?? ($v->settings['stripe_session_id'] ?? null)])])->save();
        $this->bezahlt($v);
        $b = Buchhaltung::fuer($this->current->getOrFail());
        if ($v->settings['rechnung_gewuenscht'] ?? true) {
            $this->rechnungAnlegen($v, $b, true);
        }
        $this->mailen($v, $b, true);
        app(Notifier::class)->send(app(Chat::class)->teamIds(), new Nachricht(
            titel: 'Bezahlt über Stripe: '.$v->user?->name, text: $v->title.', '.$v->betragText().($v->herkunft ? "\nKam über: ".$v->herkunft : ''),
            url: ($mm = $v->user?->membershipIn()) ? route('coachees.show', $mm) : url('/coach'), anlass: 'system', tag: 'verkauf-'.$v->id,
        ));
    }

    /**
     * In die Programme des Angebots aufnehmen, bei 1:1 mit zusaetzlichen Sitzungen. Ein 1:1-Angebot ohne
     * Programme steht fuer die Begleitung je Person: die entsteht hier (oder wird weiterverwendet).
     */
    protected function programmeGeben(User $user, Offer $offer, int $sitzungen, ?Entitlement $e = null): void
    {
        if ($offer->type === 'one_on_one' && $offer->programs->isEmpty()) {
            $p = Program::where('type', 'one_on_one')->whereHas('members', fn ($q) => $q->where('user_id', $user->id))->first();
            if (! $p) {
                $gesamt = $sitzungen ?: (int) ($offer->settings['sitzungen'] ?? 0);
                $p = Program::create([
                    'slug' => Str::slug('1-1 '.$user->name.' '.Str::lower(Str::random(4))),
                    'title' => '1:1 Begleitung '.$user->name,
                    'type' => 'one_on_one', 'pacing' => 'none', 'is_published' => true,
                    'settings' => ['sitzungen_gesamt' => $gesamt ?: null, 'teilen' => true],
                ]);
                $this->access->join($user, $p, 'participant', $e);

                return;
            }
            $pm = $this->access->join($user, $p, 'participant', $e);
            if ($sitzungen > 0) {
                $pm->forceFill(['settings' => array_merge($pm->settings ?? [], ['sitzungen_extra' => (int) ($pm->settings['sitzungen_extra'] ?? 0) + $sitzungen])])->save();
            }

            return;
        }
        foreach ($offer->programs as $p) {
            $pm = $this->access->join($user, $p, 'participant', $e);
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
        $v->forceFill(['status' => 'bezahlt', 'bezahlt_am' => now(), 'settings' => array_merge($v->settings ?? [], ['warten_auf_zahlung' => false])])->save();
        $e = $v->entitlement;
        if ($e && $e->status === 'pending' && $v->offer) {
            $this->zugang->grant($v->user, $v->offer, $e->source, $e->source_ref, $e->starts_at ?? now(), $e->ends_at, true);
            $this->programmeGeben($v->user, $v->offer, (int) ($v->settings['sitzungen'] ?? 0), $e->fresh());
        }
    }
}

<?php

namespace App\Shop;

use App\Chat\Chat;
use App\Models\Entitlement;
use App\Models\Offer;
use App\Models\User;
use App\Models\Verkauf;
use App\Notifications\Nachricht;
use App\Notifications\Notifier;
use App\Tenancy\CurrentTenant;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Abos ueber Stripe: der erste Kauf laeuft wie in der Kasse, danach kommen die Ereignisse aus dem Webhook.
 * Der Zugang (Entitlement, source stripe, source_ref = Abo-ID) laeuft bis zum Ende der bezahlten Periode
 * plus Kulanz; jede weitere Zahlung verlaengert ihn und legt einen neuen Verkauf (Quittung) an. Kuendigt die
 * Person im Kundenportal, bleibt der Zugang bis zum Periodenende und endet dann. Zahlt eine Abbuchung nicht,
 * bekommen Person und Team Bescheid; Stripe versucht es von selbst weiter.
 */
class Abo
{
    public const KULANZ_TAGE = 3;

    public function __construct(protected CurrentTenant $current, protected Stripe $stripe, protected Verkaufen $verkaufen, protected Zugang $zugang, protected Notifier $notifier) {}

    /** Checkout im Abo-Modus abgeschlossen: Kundin und Abo merken, Zugang bis zum Periodenende. */
    public function gestartet(Verkauf $v, array $session): void
    {
        $aboId = (string) ($session['subscription'] ?? '');
        Stripe::kundeMerken($v->user, $session['customer'] ?? null);
        $ende = $this->periodenEnde($aboId);
        $v->forceFill(['settings' => array_merge($v->settings ?? [], ['abo' => true, 'stripe_subscription_id' => $aboId ?: null, 'stripe_customer_id' => $session['customer'] ?? null, 'periode_bis' => $ende?->toIso8601String()])])->save();
        if ($e = $v->entitlement) {
            $e->forceFill(['source' => 'stripe', 'source_ref' => $aboId ?: $e->source_ref, 'ends_at' => $ende ? $ende->copy()->addDays(self::KULANZ_TAGE) : null])->save();
        }
        $this->verkaufen->stripeBezahlt($v, $session);
    }

    /** invoice.paid mit Abo: Zugang verlaengern, neuen Verkauf als Quittung (nicht bei der ersten Rechnung, die gehoert zur Kasse). */
    public function verlaengert(array $invoice): ?Verkauf
    {
        $aboId = (string) ($invoice['subscription'] ?? '');
        if ($aboId === '' || ($invoice['billing_reason'] ?? '') === 'subscription_create') {
            return null;
        }
        $e = Entitlement::where('source', 'stripe')->where('source_ref', $aboId)->latest('id')->first();
        if (! $e) {
            Log::warning('Stripe-Abo ohne Zugang', ['abo' => $aboId, 'tenant' => $this->current->id()]);

            return null;
        }
        $bis = $this->periodenEndeAusRechnung($invoice) ?? $this->periodenEnde($aboId);
        $e->forceFill(['status' => 'active', 'ends_at' => $bis ? $bis->copy()->addDays(self::KULANZ_TAGE) : null])->save();

        $rechnungId = (string) ($invoice['id'] ?? '');
        if ($rechnungId !== '' && Verkauf::where('settings->stripe_invoice_id', $rechnungId)->exists()) {
            return null;   // schon verbucht
        }
        $vorher = Verkauf::where('entitlement_id', $e->id)->latest('id')->first();
        $betrag = round(((int) ($invoice['amount_paid'] ?? 0)) / 100, 2);
        $v = Verkauf::create([
            'user_id' => $e->user_id, 'offer_id' => $e->offer_id, 'entitlement_id' => $e->id, 'created_by' => null,
            'title' => ($e->offer?->title ?? $vorher?->title ?? 'Abo').' (Verlängerung)', 'betrag' => $betrag, 'waehrung' => strtoupper((string) ($invoice['currency'] ?? $vorher?->waehrung ?? 'CHF')),
            'zahlungsart' => 'stripe', 'status' => 'bezahlt', 'bezahlt_am' => now(), 'herkunft' => 'abo',
            'settings' => ['abo' => true, 'stripe_subscription_id' => $aboId, 'stripe_invoice_id' => $rechnungId ?: null, 'stripe_customer_id' => $invoice['customer'] ?? null, 'periode_bis' => $bis?->toIso8601String(), 'rechnung_gewuenscht' => (bool) ($vorher?->settings['rechnung_gewuenscht'] ?? true)],
        ]);
        $b = Buchhaltung::fuer($this->current->getOrFail());
        if ($v->settings['rechnung_gewuenscht'] ?? true) {
            $this->verkaufen->rechnungAnlegen($v, $b, true);
        }
        if ($v->user) {
            try {
                $this->verkaufen->mailen($v, $b, false);
            } catch (\Throwable $ex) {
                report($ex);
            }
        }

        return $v;
    }

    /** customer.subscription.deleted: Zugang endet jetzt (bzw. mit dem Periodenende, wenn das spaeter liegt). */
    public function beendet(array $abo): int
    {
        $aboId = (string) ($abo['id'] ?? '');
        $ende = isset($abo['current_period_end']) ? Carbon::createFromTimestamp((int) $abo['current_period_end']) : now();
        $n = $this->zugang->revoke('stripe', $aboId, $ende->isFuture() ? $ende : now(), 'cancelled');
        if ($n && ($e = Entitlement::where('source', 'stripe')->where('source_ref', $aboId)->latest('id')->first()) && $e->user) {
            $this->notifier->send(app(Chat::class)->teamIds(), new Nachricht(
                titel: 'Abo beendet: '.$e->user->name, text: ($e->offer?->title ?? 'Abo').', Zugang bis '.$ende->translatedFormat('j. F Y').'.',
                url: ($mm = $e->user->membershipIn()) ? route('coachees.show', $mm) : url('/coach'), anlass: 'system', tag: 'abo-ende-'.$e->id,
            ));
        }

        return $n;
    }

    /** customer.subscription.updated: Kuendigung zum Periodenende oder Wiederaufnahme vermerken. */
    public function geaendert(array $abo): void
    {
        $aboId = (string) ($abo['id'] ?? '');
        $e = Entitlement::where('source', 'stripe')->where('source_ref', $aboId)->latest('id')->first();
        if (! $e) {
            return;
        }
        $v = Verkauf::where('entitlement_id', $e->id)->latest('id')->first();
        $v?->forceFill(['settings' => array_merge($v->settings ?? [], ['abo_gekuendigt' => (bool) ($abo['cancel_at_period_end'] ?? false), 'abo_status' => $abo['status'] ?? null])])->save();
        if (($abo['status'] ?? null) === 'active' && isset($abo['current_period_end'])) {
            $e->forceFill(['status' => 'active', 'ends_at' => Carbon::createFromTimestamp((int) $abo['current_period_end'])->addDays(self::KULANZ_TAGE)])->save();
        }
    }

    /** invoice.payment_failed: Person und Team informieren, Stripe versucht es weiter (Kulanz im Zugang). */
    public function zahlungFehlgeschlagen(array $invoice): void
    {
        $aboId = (string) ($invoice['subscription'] ?? '');
        $e = $aboId !== '' ? Entitlement::where('source', 'stripe')->where('source_ref', $aboId)->latest('id')->first() : null;
        if (! $e || ! $e->user) {
            return;
        }
        $this->notifier->send([$e->user], new Nachricht(
            titel: 'Deine Abo-Zahlung hat nicht geklappt',
            text: 'Die Abbuchung für '.($e->offer?->title ?? 'dein Abo').' ist nicht durchgegangen. Bitte prüf deine Zahlungsmethode, dann bleibt alles offen.',
            url: route('abo.portal'), anlass: 'system', tag: 'abo-zahlung-'.$e->id, mailImmer: true, knopf: 'Zahlungsmethode prüfen',
        ));
        $this->notifier->send(app(Chat::class)->teamIds(), new Nachricht(
            titel: 'Abo-Zahlung fehlgeschlagen: '.$e->user->name, text: ($e->offer?->title ?? 'Abo').'. Stripe versucht es weiter, die Person hat Bescheid.',
            url: ($mm = $e->user->membershipIn()) ? route('coachees.show', $mm) : url('/coach'), anlass: 'system', tag: 'abo-zahlung-team-'.$e->id,
        ));
    }

    /** Laufende Abos einer Person (Zugaenge aus Stripe-Abos). */
    public function laufendeFuer(User $user)
    {
        return Entitlement::where('user_id', $user->id)->where('source', 'stripe')->where('status', 'active')->with('offer')->get()->filter(fn ($e) => $e->offer?->istAbo());
    }

    /**
     * Bestehendes Abo umziehen (aus WooCommerce): Stripe-Kundin ist bekannt, das Abo entsteht in Stripe mit
     * erster Abbuchung am naechsten Faelligkeitstag, der Zugang gilt ab sofort.
     */
    public function umziehen(User $user, Offer $offer, string $kunde, \DateTimeInterface $naechsteAbbuchung, ?float $betrag = null, string $waehrung = 'CHF', bool $trocken = false): array
    {
        $intervall = $offer->aboIntervall() ?: 'monat';
        $betrag ??= $offer->preis($waehrung) ?? 0.0;
        if ($trocken) {
            return ['trocken' => true, 'betrag' => $betrag, 'waehrung' => $waehrung, 'intervall' => $intervall, 'ab' => Carbon::instance($naechsteAbbuchung)->toDateTimeString()];
        }
        $v = $this->verkaufen->verkaufen(null, $user, $offer, ['betrag' => $betrag, 'waehrung' => $waehrung, 'zahlungsart' => 'bezahlt', 'rechnung' => false, 'mail' => false, 'herkunft' => 'abo-umzug', 'notiz' => 'Abo aus WooCommerce umgezogen, erste Abbuchung in Stripe am '.Carbon::instance($naechsteAbbuchung)->translatedFormat('j. F Y')]);
        $abo = $this->stripe->aboAnlegen($kunde, $v, $intervall, $naechsteAbbuchung);
        Stripe::kundeMerken($user, $kunde);
        $ende = isset($abo['current_period_end']) ? Carbon::createFromTimestamp((int) $abo['current_period_end']) : Carbon::instance($naechsteAbbuchung);
        $v->forceFill(['settings' => array_merge($v->settings ?? [], ['abo' => true, 'stripe_subscription_id' => $abo['id'] ?? null, 'stripe_customer_id' => $kunde, 'periode_bis' => $ende->toIso8601String()])])->save();
        $v->entitlement?->forceFill(['source' => 'stripe', 'source_ref' => $abo['id'] ?? $v->entitlement->source_ref, 'ends_at' => $ende->copy()->addDays(self::KULANZ_TAGE)])->save();

        return ['abo' => $abo['id'] ?? null, 'verkauf' => $v->id, 'zugang_bis' => $ende->toDateTimeString()];
    }

    protected function periodenEnde(string $aboId): ?Carbon
    {
        if ($aboId === '') {
            return null;
        }
        try {
            $abo = $this->stripe->abo($aboId);
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
        $ts = $abo['current_period_end'] ?? ($abo['items']['data'][0]['current_period_end'] ?? null);

        return $ts ? Carbon::createFromTimestamp((int) $ts) : null;
    }

    protected function periodenEndeAusRechnung(array $invoice): ?Carbon
    {
        $ts = $invoice['lines']['data'][0]['period']['end'] ?? null;

        return $ts ? Carbon::createFromTimestamp((int) $ts) : null;
    }
}

<?php

namespace App\Shop;

use App\Models\User;
use App\Models\Verkauf;
use App\Tenancy\CurrentTenant;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Stripe je Mandant (Schluessel unter Verbindungen): Checkout fuer Karte und Twint, ohne SDK ueber die
 * HTTP-Schnittstelle. Twint und weitere Zahlarten schaltet die Coachin im Stripe-Dashboard frei.
 */
class Stripe
{
    public function __construct(protected CurrentTenant $current) {}

    public function konfiguriert(): bool
    {
        return filled($this->current->get()?->setting('stripe.secret_key'));
    }

    public function publicKey(): ?string
    {
        return $this->current->get()?->setting('stripe.public_key');
    }

    /** Checkout-Sitzung fuer einen Verkauf: gibt ['id', 'url'] zurueck. */
    public function checkout(Verkauf $v, string $erfolgUrl, string $abbruchUrl): array
    {
        $tenant = $this->current->getOrFail();
        $meta = ['verkauf_id' => (string) $v->id, 'tenant_id' => (string) $tenant->id, 'user_id' => (string) $v->user_id, 'offer_id' => (string) $v->offer_id];
        $intervall = $v->offer?->aboIntervall();
        $preis = ['currency' => strtolower($v->waehrung), 'unit_amount' => (int) round((float) $v->betrag * 100), 'product_data' => ['name' => $v->title]];
        if ($intervall) {
            $preis['recurring'] = ['interval' => $intervall === 'jahr' ? 'year' : 'month'];
        }
        $params = [
            'mode' => $intervall ? 'subscription' : 'payment',
            'locale' => 'de',
            'client_reference_id' => (string) $v->id,
            'line_items' => [['quantity' => 1, 'price_data' => $preis]],
            'metadata' => $meta,
            'success_url' => $erfolgUrl,
            'cancel_url' => $abbruchUrl,
        ];
        // Bekannte Stripe-Kundin wiederverwenden, sonst Adresse vorbelegen
        if ($kunde = self::kundeVon($v->user)) {
            $params['customer'] = $kunde;
        } else {
            $params['customer_email'] = $v->user?->email;
        }
        if ($intervall) {
            $params['subscription_data'] = ['metadata' => $meta, 'description' => $v->title.' · '.$v->user?->name];
        } else {
            $params['payment_intent_data'] = ['description' => $v->title.' · '.$v->user?->name, 'metadata' => $meta];
        }
        $s = $this->req('POST', '/v1/checkout/sessions', $params);
        if (empty($s['url'])) {
            throw new RuntimeException('Stripe hat keine Kassenseite geliefert.');
        }

        return ['id' => (string) $s['id'], 'url' => (string) $s['url']];
    }

    public function session(string $id): array
    {
        return $this->req('GET', '/v1/checkout/sessions/'.$id);
    }

    public function abo(string $id): array
    {
        return $this->req('GET', '/v1/subscriptions/'.$id);
    }

    /** Abo zum Ende der Laufzeit beenden (die Person behaelt den Zugang bis dahin). */
    public function aboKuendigen(string $id): array
    {
        return $this->req('POST', '/v1/subscriptions/'.$id, ['cancel_at_period_end' => 'true']);
    }

    /** Kundenportal: Zahlungsmittel aendern, Rechnungen sehen, Abo kuendigen. Gibt die URL zurueck. */
    public function portal(string $kunde, string $zurueckUrl): string
    {
        $s = $this->req('POST', '/v1/billing_portal/sessions', ['customer' => $kunde, 'return_url' => $zurueckUrl]);
        if (empty($s['url'])) {
            throw new RuntimeException('Stripe hat kein Kundenportal geliefert.');
        }

        return (string) $s['url'];
    }

    /**
     * Abo fuer eine bestehende Stripe-Kundin direkt anlegen (Umzug aus WooCommerce): die gespeicherte Zahlungsmethode
     * bleibt, die erste Abbuchung kommt zum naechsten Termin ($ab), bis dahin ohne Berechnung.
     */
    public function aboAnlegen(string $kunde, Verkauf $v, string $intervall, \DateTimeInterface $ab): array
    {
        $tenant = $this->current->getOrFail();

        return $this->req('POST', '/v1/subscriptions', [
            'customer' => $kunde,
            'items' => [['price_data' => [
                'currency' => strtolower($v->waehrung), 'unit_amount' => (int) round((float) $v->betrag * 100),
                'recurring' => ['interval' => $intervall === 'jahr' ? 'year' : 'month'], 'product_data' => ['name' => $v->title],
            ]]],
            'billing_cycle_anchor' => $ab->getTimestamp(),
            'proration_behavior' => 'none',
            'trial_end' => $ab->getTimestamp(),
            'description' => $v->title.' · '.$v->user?->name,
            'metadata' => ['verkauf_id' => (string) $v->id, 'tenant_id' => (string) $tenant->id, 'user_id' => (string) $v->user_id, 'offer_id' => (string) $v->offer_id, 'umzug' => '1'],
        ]);
    }

    /** Stripe-Kundennummer der Person im Mandanten (memberships.settings.stripe_customer_id). */
    public static function kundeVon(?User $user): ?string
    {
        $id = $user?->membershipIn()?->settings['stripe_customer_id'] ?? null;

        return filled($id) ? (string) $id : null;
    }

    public static function kundeMerken(User $user, ?string $kunde): void
    {
        if (! filled($kunde) || ! ($m = $user->membershipIn())) {
            return;
        }
        $m->forceFill(['settings' => array_merge($m->settings ?? [], ['stripe_customer_id' => $kunde])])->saveQuietly();
    }

    public function req(string $method, string $pfad, array $params = []): array
    {
        $key = (string) $this->current->get()?->setting('stripe.secret_key');
        if ($key === '') {
            throw new RuntimeException('Stripe ist nicht eingerichtet.');
        }
        $r = Http::withToken($key)->asForm()->timeout(20)->send($method, 'https://api.stripe.com'.$pfad, $method === 'GET' ? ['query' => $params] : ['form_params' => $params]);
        if ($r->failed()) {
            throw new RuntimeException('Stripe: '.($r->json('error.message') ?: 'Fehler '.$r->status()));
        }

        return (array) $r->json();
    }
}

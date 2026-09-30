<?php

namespace App\Shop;

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
        $s = $this->req('POST', '/v1/checkout/sessions', [
            'mode' => 'payment',
            'locale' => 'de',
            'client_reference_id' => (string) $v->id,
            'customer_email' => $v->user?->email,
            'line_items' => [[
                'quantity' => 1,
                'price_data' => ['currency' => strtolower($v->waehrung), 'unit_amount' => (int) round((float) $v->betrag * 100), 'product_data' => ['name' => $v->title]],
            ]],
            'metadata' => ['verkauf_id' => (string) $v->id, 'tenant_id' => (string) $tenant->id],
            'payment_intent_data' => ['description' => $v->title.' · '.$v->user?->name, 'metadata' => ['verkauf_id' => (string) $v->id, 'tenant_id' => (string) $tenant->id]],
            'success_url' => $erfolgUrl,
            'cancel_url' => $abbruchUrl,
        ]);
        if (empty($s['url'])) {
            throw new RuntimeException('Stripe hat keine Kassenseite geliefert.');
        }

        return ['id' => (string) $s['id'], 'url' => (string) $s['url']];
    }

    public function session(string $id): array
    {
        return $this->req('GET', '/v1/checkout/sessions/'.$id);
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

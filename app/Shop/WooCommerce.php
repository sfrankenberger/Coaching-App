<?php

namespace App\Shop;

use App\Models\Offer;
use App\Models\OfferProduct;
use App\Models\Tenant;
use App\Models\WebhookLog;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * WooCommerce-Webhooks: Bestellungen und Abos werden zu Zugaengen.
 *
 * Einrichtung im Shop (WooCommerce > Einstellungen > Erweitert > Webhooks):
 *   Thema "Bestellung aktualisiert" und, bei Abos, "Abonnement aktualisiert",
 *   Ziel-URL https://app.example.ch/hooks/woocommerce, Geheimnis = settings.shop.webhook_secret.
 *
 * Produkte werden ueber offer_products (source woocommerce, external_id = Produkt- oder Varianten-ID)
 * Angeboten zugeordnet. Angebote vom Typ "club" gelten als Abo und reagieren nur auf Abo-Ereignisse.
 */
class WooCommerce
{
    public const ORDER_ACTIVE = ['completed', 'processing'];

    public const ORDER_ENDED = ['refunded', 'cancelled', 'failed', 'trash'];

    public const SUB_ACTIVE = ['active', 'pending-cancel'];

    public const SUB_ENDED = ['on-hold', 'cancelled', 'expired', 'trash', 'switched'];

    public function __construct(protected Zugang $zugang) {}

    public static function verify(Tenant $tenant, string $body, ?string $signature): bool
    {
        $secret = (string) $tenant->setting('shop.webhook_secret');
        if ($secret === '' || $signature === null) {
            return false;
        }

        return hash_equals(base64_encode(hash_hmac('sha256', $body, $secret, true)), $signature);
    }

    /** Verarbeitet ein Ereignis und gibt [status, note] zurueck. Schreibt ins Webhook-Protokoll. */
    public function handle(string $topic, array $payload): array
    {
        [$status, $note] = match (true) {
            str_starts_with($topic, 'order.') => $this->order($payload),
            str_starts_with($topic, 'subscription.') => $this->subscription($payload),
            default => ['ignored', 'Thema nicht behandelt'],
        };

        WebhookLog::create([
            'source' => 'woocommerce',
            'topic' => $topic,
            'external_id' => isset($payload['id']) ? (string) $payload['id'] : null,
            'status' => $status,
            'note' => $note,
            'payload' => $this->trim($payload),
        ]);

        return [$status, $note];
    }

    protected function order(array $o): array
    {
        $status = (string) ($o['status'] ?? '');
        $offers = $this->offersFor($o['line_items'] ?? [])->reject(fn (Offer $x) => $x->type === 'club');
        if ($offers->isEmpty()) {
            return ['ignored', 'Kein zugeordnetes Produkt (Abo-Angebote laufen über subscription.*)'];
        }
        $ref = 'order-'.($o['id'] ?? '?');

        if (in_array($status, self::ORDER_ACTIVE, true)) {
            $email = (string) ($o['billing']['email'] ?? '');
            if ($email === '') {
                return ['error', 'Bestellung ohne Mailadresse'];
            }
            [$user, $neu] = $this->zugang->ensureUser($email, trim(($o['billing']['first_name'] ?? '').' '.($o['billing']['last_name'] ?? '')));
            $paid = ! empty($o['date_paid_gmt']) ? Carbon::parse($o['date_paid_gmt'], 'UTC') : now();
            foreach ($offers as $offer) {
                $this->zugang->grant($user, $offer, 'woocommerce', $ref, $paid, notify: ! $neu);
            }
            if ($neu) {
                $this->zugang->welcome($user, $offers->first());
            }

            return ['ok', 'Zugang: '.$offers->pluck('title')->implode(', ').' für '.$email];
        }
        if (in_array($status, self::ORDER_ENDED, true)) {
            $n = $this->zugang->revoke('woocommerce', $ref, status: 'cancelled');

            return ['ok', "Beendet: {$n} Zugang/Zugänge ({$status})"];
        }

        return ['ignored', "Bestellstatus {$status}"];
    }

    protected function subscription(array $s): array
    {
        $status = (string) ($s['status'] ?? '');
        $offers = $this->offersFor($s['line_items'] ?? []);
        if ($offers->isEmpty()) {
            return ['ignored', 'Kein zugeordnetes Produkt'];
        }
        $ref = 'sub-'.($s['id'] ?? '?');

        if (in_array($status, self::SUB_ACTIVE, true)) {
            $email = (string) ($s['billing']['email'] ?? '');
            if ($email === '') {
                return ['error', 'Abo ohne Mailadresse'];
            }
            [$user, $neu] = $this->zugang->ensureUser($email, trim(($s['billing']['first_name'] ?? '').' '.($s['billing']['last_name'] ?? '')));
            $ends = $status === 'pending-cancel' && ! empty($s['end_date_gmt']) ? Carbon::parse($s['end_date_gmt'], 'UTC') : null;
            $starts = ! empty($s['start_date_gmt']) ? Carbon::parse($s['start_date_gmt'], 'UTC') : now();
            foreach ($offers as $offer) {
                $e = $this->zugang->grant($user, $offer, 'woocommerce', $ref, $starts, $ends, notify: ! $neu);
                if ($ends === null && $e->ends_at) {
                    $e->forceFill(['ends_at' => null])->save();   // Abo: laeuft, bis es endet
                }
            }
            if ($neu) {
                $this->zugang->welcome($user, $offers->first());
            }

            return ['ok', 'Abo aktiv: '.$offers->pluck('title')->implode(', ').' für '.$email];
        }
        if (in_array($status, self::SUB_ENDED, true)) {
            $n = $this->zugang->revoke('woocommerce', $ref, status: $status === 'on-hold' ? 'ended' : 'cancelled');

            return ['ok', "Abo beendet ({$status}): {$n} Zugang/Zugänge"];
        }

        return ['ignored', "Abostatus {$status}"];
    }

    /** Angebote zu den Produkten der Positionen (Produkt-ID oder Varianten-ID). */
    protected function offersFor(array $items): Collection
    {
        $ids = collect($items)->flatMap(fn ($i) => [(string) ($i['product_id'] ?? ''), (string) ($i['variation_id'] ?? '')])->filter(fn ($x) => $x !== '' && $x !== '0')->unique()->values();
        if ($ids->isEmpty()) {
            return collect();
        }

        return OfferProduct::where('source', 'woocommerce')->whereIn('external_id', $ids)->with('offer.programs')->get()
            ->map(fn (OfferProduct $p) => $p->offer)->filter(fn ($o) => $o && $o->is_active)->unique('id')->values();
    }

    protected function trim(array $p): array
    {
        return [
            'id' => $p['id'] ?? null,
            'status' => $p['status'] ?? null,
            'email' => $p['billing']['email'] ?? null,
            'items' => collect($p['line_items'] ?? [])->map(fn ($i) => ['product_id' => $i['product_id'] ?? null, 'variation_id' => $i['variation_id'] ?? null, 'name' => $i['name'] ?? null])->all(),
        ];
    }
}

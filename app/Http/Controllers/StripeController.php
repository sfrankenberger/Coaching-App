<?php

namespace App\Http\Controllers;

use App\Models\Verkauf;
use App\Shop\Abo;
use App\Shop\Verkaufen;
use App\Tenancy\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Stripe-Webhook je Mandant: POST /hooks/stripe. Prueft die Signatur mit settings.stripe.webhook_secret.
 * Kasse: checkout.session.completed schaltet frei. Abos: invoice.paid verlaengert, invoice.payment_failed meldet,
 * customer.subscription.updated/deleted vermerken Kuendigung und Ende.
 */
class StripeController extends Controller
{
    public function webhook(Request $request, CurrentTenant $current, Verkaufen $verkaufen, Abo $abo): JsonResponse
    {
        $secret = (string) $current->get()?->setting('stripe.webhook_secret');
        if ($secret === '') {
            Log::warning('Stripe-Webhook ohne Webhook-Secret im Mandanten', ['tenant' => $current->id()]);

            return response()->json(['error' => 'Kein Webhook-Secret hinterlegt.'], 404);
        }
        $header = (string) $request->header('Stripe-Signature');
        if (! self::signaturGueltig($request->getContent(), $header, $secret, $grund)) {
            // Abgelehnte Aufrufe protokollieren, damit sich ein falsches Secret oder eine falsche Uhr finden laesst
            Log::warning('Stripe-Webhook abgelehnt: '.$grund, ['tenant' => $current->id(), 'header' => mb_substr($header, 0, 60), 'secret_endet' => substr($secret, -4), 'laenge' => strlen($request->getContent())]);

            return response()->json(['error' => 'Signatur ungültig: '.$grund], 400);
        }

        $event = $request->json()->all();
        Log::info('Stripe-Webhook', ['tenant' => $current->id(), 'type' => $event['type'] ?? null, 'id' => $event['id'] ?? null]);

        $typ = (string) ($event['type'] ?? '');
        $obj = (array) ($event['data']['object'] ?? []);
        if (in_array($typ, ['checkout.session.completed', 'checkout.session.async_payment_succeeded'], true) && ($obj['payment_status'] ?? null) === 'paid') {
            $id = (int) ($obj['metadata']['verkauf_id'] ?? $obj['client_reference_id'] ?? 0);
            $v = $id ? Verkauf::find($id) : null;
            if ($v && $v->zahlungsart === 'stripe' && (string) ($obj['metadata']['tenant_id'] ?? $current->id()) === (string) $current->id()) {
                ($obj['mode'] ?? 'payment') === 'subscription' ? $abo->gestartet($v, $obj) : $verkaufen->stripeBezahlt($v, $obj);
            }
        } elseif ($typ === 'invoice.paid' || $typ === 'invoice.payment_succeeded') {
            $abo->verlaengert($obj);
        } elseif ($typ === 'invoice.payment_failed') {
            $abo->zahlungFehlgeschlagen($obj);
        } elseif ($typ === 'customer.subscription.deleted') {
            $abo->beendet($obj);
        } elseif ($typ === 'customer.subscription.updated') {
            $abo->geaendert($obj);
        }

        return response()->json(['received' => true]);
    }

    /** Stripe-Signatur: t=Zeit,v1=HMAC-SHA256 ueber "t.payload", hoechstens fuenf Minuten alt. */
    public static function signaturGueltig(string $payload, string $header, string $secret, ?string &$grund = null): bool
    {
        $teile = [];
        foreach (explode(',', $header) as $t) {
            [$k, $v] = array_pad(explode('=', trim($t), 2), 2, '');
            $teile[$k][] = $v;
        }
        $zeit = (int) ($teile['t'][0] ?? 0);
        if (! $zeit) {
            $grund = $header === '' ? 'keine Stripe-Signature-Kopfzeile' : 'Kopfzeile ohne Zeitstempel';

            return false;
        }
        if (abs(time() - $zeit) > 300) {
            $grund = 'Zeitstempel weicht '.abs(time() - $zeit).' Sekunden ab (Uhr des Servers?)';

            return false;
        }
        $erwartet = hash_hmac('sha256', $zeit.'.'.$payload, trim($secret));
        foreach ($teile['v1'] ?? [] as $sig) {
            if (hash_equals($erwartet, $sig)) {
                return true;
            }
        }
        $grund = ($teile['v1'] ?? []) === [] ? 'keine v1-Signatur' : 'Signatur passt nicht zum hinterlegten Webhook-Secret';

        return false;
    }
}

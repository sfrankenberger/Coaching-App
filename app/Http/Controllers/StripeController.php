<?php

namespace App\Http\Controllers;

use App\Models\Verkauf;
use App\Shop\Verkaufen;
use App\Tenancy\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Stripe-Webhook je Mandant: POST /hooks/stripe. Prueft die Signatur mit settings.stripe.webhook_secret.
 * Die Ereignisse werden bestaetigt und protokolliert; die Auswertung (Kasse, Abos) kommt mit Etappe 10.
 */
class StripeController extends Controller
{
    public function webhook(Request $request, CurrentTenant $current, Verkaufen $verkaufen): JsonResponse
    {
        $secret = (string) $current->get()?->setting('stripe.webhook_secret');
        abort_if($secret === '', 404);
        abort_unless(self::signaturGueltig($request->getContent(), (string) $request->header('Stripe-Signature'), $secret), 400, 'Signatur ungültig.');

        $event = $request->json()->all();
        Log::info('Stripe-Webhook', ['tenant' => $current->id(), 'type' => $event['type'] ?? null, 'id' => $event['id'] ?? null]);

        $typ = (string) ($event['type'] ?? '');
        $obj = (array) ($event['data']['object'] ?? []);
        if (in_array($typ, ['checkout.session.completed', 'checkout.session.async_payment_succeeded'], true) && ($obj['payment_status'] ?? null) === 'paid') {
            $id = (int) ($obj['metadata']['verkauf_id'] ?? $obj['client_reference_id'] ?? 0);
            $v = $id ? Verkauf::find($id) : null;
            if ($v && $v->zahlungsart === 'stripe' && (string) ($obj['metadata']['tenant_id'] ?? $current->id()) === (string) $current->id()) {
                $verkaufen->stripeBezahlt($v, $obj);
            }
        }

        return response()->json(['received' => true]);
    }

    /** Stripe-Signatur: t=Zeit,v1=HMAC-SHA256 ueber "t.payload", hoechstens fuenf Minuten alt. */
    public static function signaturGueltig(string $payload, string $header, string $secret): bool
    {
        $teile = [];
        foreach (explode(',', $header) as $t) {
            [$k, $v] = array_pad(explode('=', trim($t), 2), 2, '');
            $teile[$k][] = $v;
        }
        $zeit = (int) ($teile['t'][0] ?? 0);
        if (! $zeit || abs(time() - $zeit) > 300) {
            return false;
        }
        $erwartet = hash_hmac('sha256', $zeit.'.'.$payload, $secret);
        foreach ($teile['v1'] ?? [] as $sig) {
            if (hash_equals($erwartet, $sig)) {
                return true;
            }
        }

        return false;
    }
}

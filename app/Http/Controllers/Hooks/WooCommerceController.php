<?php

namespace App\Http\Controllers\Hooks;

use App\Http\Controllers\Controller;
use App\Shop\WooCommerce;
use App\Tenancy\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** POST /hooks/woocommerce, je Mandant ueber die Domain, geprueft ueber die Woo-Signatur. */
class WooCommerceController extends Controller
{
    public function __invoke(Request $request, CurrentTenant $current, WooCommerce $woo): JsonResponse
    {
        $tenant = $current->getOrFail();
        $body = $request->getContent();

        // Beim Einrichten schickt Woo einen Ping (webhook_id=...) ohne Signatur. Er loest nichts aus.
        if (preg_match('~^webhook_id=\d+$~', trim($body))) {
            return response()->json(['ok' => true, 'note' => 'ping']);
        }

        abort_unless(WooCommerce::verify($tenant, $body, $request->header('X-WC-Webhook-Signature')), 403);

        $payload = json_decode($body, true);
        if (! is_array($payload) || $payload === []) {
            return response()->json(['ok' => true, 'note' => 'ping']);
        }

        [$status, $note] = $woo->handle((string) $request->header('X-WC-Webhook-Topic', ''), $payload);

        return response()->json(['ok' => $status !== 'error', 'status' => $status, 'note' => $note]);
    }
}

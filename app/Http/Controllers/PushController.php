<?php

namespace App\Http\Controllers;

use App\Models\PushSubscription;
use App\Notifications\WebPushChannel;
use App\Tenancy\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Web-Push-Abos: Schluessel holen, Geraet an- und abmelden. */
class PushController extends Controller
{
    public function __construct(protected CurrentTenant $current) {}

    public function schluessel(): JsonResponse
    {
        try {
            $keys = WebPushChannel::ensureKeys($this->current->getOrFail());
        } catch (\Throwable $e) {
            return response()->json(['fehler' => 'Push ist auf diesem Server nicht eingerichtet.'], 503);
        }

        return response()->json(['publicKey' => $keys['public']]);
    }

    public function abo(Request $request): JsonResponse
    {
        $data = $request->validate([
            'endpoint' => ['required', 'url', 'max:1000'],
            'keys.p256dh' => ['required', 'string', 'max:255'],
            'keys.auth' => ['required', 'string', 'max:255'],
        ]);

        PushSubscription::updateOrCreate(
            ['endpoint_hash' => hash('sha256', $data['endpoint'])],
            ['user_id' => $request->user()->id, 'endpoint' => $data['endpoint'], 'p256dh' => $data['keys']['p256dh'], 'auth' => $data['keys']['auth'], 'user_agent' => mb_substr((string) $request->userAgent(), 0, 200)],
        );

        return response()->json(['ok' => true, 'anzahl' => PushSubscription::where('user_id', $request->user()->id)->count()]);
    }

    public function weg(Request $request): JsonResponse
    {
        $data = $request->validate(['endpoint' => ['nullable', 'string', 'max:1000']]);
        $q = PushSubscription::where('user_id', $request->user()->id);
        if (filled($data['endpoint'] ?? null)) {
            $q->where('endpoint_hash', hash('sha256', $data['endpoint']));
        }
        $q->delete();

        return response()->json(['ok' => true, 'anzahl' => PushSubscription::where('user_id', $request->user()->id)->count()]);
    }
}

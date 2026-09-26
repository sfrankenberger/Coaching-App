<?php

namespace App\Notifications;

use App\Models\PushSubscription;
use App\Models\Tenant;
use App\Tenancy\Branding;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\VAPID;
use Minishlink\WebPush\WebPush;

/**
 * Web Push (VAPID) je Mandant. Schluessel in tenants.settings.push.vapid.
 * Abgelaufene Abos werden beim Senden entfernt.
 */
class WebPushChannel
{
    public function send(object $notifiable, Notification $notification): void
    {
        if (! $notification instanceof AppNotification) {
            return;
        }

        $notification->withTenant(function (?Tenant $tenant) use ($notifiable, $notification) {
            $keys = self::keysFor($tenant);
            if (! $keys) {
                return;
            }
            $subs = PushSubscription::where('user_id', $notifiable->id)->get();
            if ($subs->isEmpty()) {
                return;
            }

            $branding = app(Branding::class);
            $data = $notification->toWebPush($notifiable);
            $payload = json_encode([
                'title' => $data['titel'],
                'body' => Str::words(strip_tags($data['text']), 24, ' ...'),
                'url' => $data['url'] ?: url('/'),
                'tag' => $data['tag'],
                'icon' => $branding->get('icon_url'),
            ], JSON_UNESCAPED_UNICODE);

            try {
                $push = new WebPush(['VAPID' => [
                    'subject' => 'mailto:'.($tenant?->setting('mail.from_address') ?: config('mail.from.address')),
                    'publicKey' => $keys['public'],
                    'privateKey' => $keys['private'],
                ]], ['TTL' => 86400]);
            } catch (\Throwable $e) {
                Log::warning('Web Push nicht verfuegbar: '.$e->getMessage());

                return;
            }

            $byEndpoint = [];
            foreach ($subs as $sub) {
                try {
                    $push->queueNotification(Subscription::create(['endpoint' => $sub->endpoint, 'keys' => ['p256dh' => $sub->p256dh, 'auth' => $sub->auth]]), $payload);
                    $byEndpoint[$sub->endpoint] = $sub;
                } catch (\Throwable $e) {
                    Log::warning('Push-Abo unbrauchbar: '.$e->getMessage());
                }
            }
            foreach ($push->flush() as $report) {
                $endpoint = (string) $report->getRequest()->getUri();
                if ($report->isSubscriptionExpired() && isset($byEndpoint[$endpoint])) {
                    $byEndpoint[$endpoint]->delete();
                }
            }
        });
    }

    /** ['public' => ..., 'private' => ...] oder null, wenn der Mandant keine Schluessel hat. */
    public static function keysFor(?Tenant $tenant): ?array
    {
        $vapid = $tenant?->setting('push.vapid');

        return is_array($vapid) && filled($vapid['public'] ?? null) && filled($vapid['private'] ?? null) ? $vapid : null;
    }

    /** Schluessel fuer einen Mandanten erzeugen und speichern (einmalig). */
    public static function ensureKeys(Tenant $tenant): array
    {
        if ($keys = self::keysFor($tenant)) {
            return $keys;
        }
        $created = VAPID::createVapidKeys();
        $settings = $tenant->settings ?? [];
        data_set($settings, 'push.vapid', ['public' => $created['publicKey'], 'private' => $created['privateKey']]);
        $tenant->forceFill(['settings' => $settings])->save();

        return self::keysFor($tenant);
    }
}

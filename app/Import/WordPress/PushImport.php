<?php

namespace App\Import\WordPress;

use App\Models\PushSubscription;
use App\Models\Tenant;
use App\Models\User;

/**
 * Push-Abos aus dem alten Bereich uebernehmen (usermeta lea_push_subs) und auf Wunsch die
 * VAPID-Schluessel (Option lea_push_vapid). Mit denselben Schluesseln erreicht die App die
 * bestehenden Abos, die Browser zeigen die Nachricht ueber den alten Service Worker an und
 * oeffnen beim Antippen die URL aus der Nachricht, also die App. Niemand muss Push neu einschalten.
 */
class PushImport
{
    public function __construct(protected Tenant $tenant, protected WordPressSource $wp, protected bool $mitSchluessel = false, protected bool $dryRun = false) {}

    public function run(?callable $report = null): array
    {
        $stats = ['personen' => 0, 'abos' => 0, 'neu' => 0, 'ohne_konto' => 0, 'schluessel' => false];

        if ($this->mitSchluessel) {
            $option = $this->wp->db()->table('options')->where('option_name', 'lea_push_vapid')->value('option_value');
            $v = WordPressSource::unserialize($option);
            if (is_array($v) && filled($v['publicKey'] ?? null) && filled($v['privateKey'] ?? null)) {
                if (! $this->dryRun) {
                    $settings = $this->tenant->settings ?? [];
                    data_set($settings, 'push.vapid', ['public' => $v['publicKey'], 'private' => $v['privateKey']]);
                    $this->tenant->forceFill(['settings' => $settings])->save();
                }
                $stats['schluessel'] = true;
                $report && $report('VAPID-Schluessel aus WordPress uebernommen');
            } else {
                $report && $report('Keine VAPID-Schluessel in WordPress gefunden');
            }
        }

        $mails = $this->wp->users()->pluck('user_email', 'ID');
        foreach ($this->wp->userMetaByKey('lea_push_subs') as $wpId => $wert) {
            $subs = WordPressSource::unserialize($wert);
            if (! is_array($subs) || $subs === []) {
                continue;
            }
            $email = mb_strtolower(trim((string) ($mails[$wpId] ?? '')));
            $user = $email ? User::where('email', $email)->first() : null;
            if (! $user || ! $user->membershipIn($this->tenant)) {
                $stats['ohne_konto']++;
                $report && $report("#{$wpId} {$email}: kein Konto in der App, uebersprungen");

                continue;
            }
            $stats['personen']++;
            foreach ($subs as $s) {
                if (empty($s['endpoint'])) {
                    continue;
                }
                $stats['abos']++;
                if ($this->dryRun) {
                    continue;
                }
                $sub = PushSubscription::firstOrNew(['endpoint_hash' => hash('sha256', $s['endpoint'])]);
                if (! $sub->exists) {
                    $stats['neu']++;
                }
                $sub->fill([
                    'user_id' => $user->id,
                    'endpoint' => $s['endpoint'],
                    'p256dh' => $s['keys']['p256dh'] ?? null,
                    'auth' => $s['keys']['auth'] ?? null,
                    'user_agent' => mb_substr((string) ($s['ua'] ?? ''), 0, 200) ?: null,
                ])->save();
            }
            $report && $report("#{$wpId} {$email}: ".count($subs).' Abos');
        }

        return $stats;
    }
}

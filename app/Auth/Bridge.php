<?php

namespace App\Auth;

use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\CurrentTenant;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Bruecke aus dem alten Mitgliederbereich: WordPress erzeugt fuer die angemeldete
 * Person einen signierten Link (HMAC mit gemeinsamem Geheimnis), 60 Sekunden gueltig,
 * einmal einloesbar. Die App prueft Signatur, Ablauf und Einmaligkeit und meldet an.
 *
 * Token = base64url(json{e: mail, t: ablauf, n: nonce, w: weiter}) . "." . hmac_sha256(hex)
 * Geheimnis: tenants.settings.bridge.secret (php84 artisan bridge:secret lea)
 */
class Bridge
{
    public const SEKUNDEN = 60;

    public function __construct(protected CurrentTenant $current) {}

    public static function secret(?Tenant $tenant): ?string
    {
        $s = (string) $tenant?->setting('bridge.secret');

        return $s !== '' ? $s : null;
    }

    /** Token bauen (fuer Tests und den Testversand, WordPress baut ihn selbst gleich). */
    public function make(string $email, ?string $weiter = null, ?int $expiresAt = null, ?string $secret = null): string
    {
        $secret ??= self::secret($this->current->getOrFail());
        $payload = ['e' => Str::lower(trim($email)), 't' => $expiresAt ?? (time() + self::SEKUNDEN), 'n' => bin2hex(random_bytes(8)), 'w' => $weiter];
        $data = self::b64(json_encode($payload));

        return $data.'.'.hash_hmac('sha256', $data, (string) $secret);
    }

    /** Token pruefen und einloesen. Gibt [User, weiter] oder null zurueck. */
    public function consume(string $token): ?array
    {
        $tenant = $this->current->getOrFail();
        $secret = self::secret($tenant);
        if (! $secret || substr_count($token, '.') !== 1) {
            return null;
        }
        [$data, $sig] = explode('.', $token, 2);
        if (! hash_equals(hash_hmac('sha256', $data, $secret), $sig)) {
            return null;
        }
        $payload = json_decode((string) base64_decode(strtr($data, '-_', '+/')), true);
        if (! is_array($payload) || empty($payload['e']) || empty($payload['t']) || empty($payload['n'])) {
            return null;
        }
        if ((int) $payload['t'] < time()) {
            return null;
        }
        // Einmaligkeit: der Nonce wird bis nach Ablauf gemerkt
        if (! Cache::add("bridge:{$tenant->id}:".preg_replace('~[^a-f0-9]~', '', (string) $payload['n']), 1, now()->addSeconds(self::SEKUNDEN * 5))) {
            return null;
        }
        $user = User::where('email', Str::lower(trim((string) $payload['e'])))->first();
        if (! $user || ! $user->hasAccessTo($tenant)) {
            return null;
        }

        return [$user, MagicLink::cleanWeiter($payload['w'] ?? null)];
    }

    public static function b64(string $s): string
    {
        return rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
    }
}

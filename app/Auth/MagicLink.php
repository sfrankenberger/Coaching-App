<?php

namespace App\Auth;

use App\Mail\MagicLinkMail;
use App\Models\LoginToken;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\CurrentTenant;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Anmelden ohne Passwort: Link per Mail, 15 Minuten gueltig, einmal verwendbar.
 * Der Link gilt nur fuer den Mandanten, auf dessen Domain er angefordert wurde.
 */
class MagicLink
{
    public const MINUTEN = 15;

    public function __construct(protected CurrentTenant $current) {}

    /**
     * Erzeugt einen Token fuer die Person und gibt die vollstaendige URL zurueck.
     */
    public function create(User $user, ?string $weiter = null, ?string $ip = null, ?int $minuten = null): string
    {
        return $this->erzeugen($user, $weiter, $ip, $minuten)['url'];
    }

    /**
     * Token mit Link und sechsstelligem Code. Der Code ist fuer die App auf dem Handy: der Link aus der Mail
     * oeffnet dort den Browser, nicht die installierte App, der Code laesst sich in der App eintippen.
     *
     * @return array{url: string, code: string}
     */
    public function erzeugen(User $user, ?string $weiter = null, ?string $ip = null, ?int $minuten = null): array
    {
        $tenant = $this->current->getOrFail();
        $plain = Str::random(48);
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        LoginToken::create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $plain),
            'code_hash' => self::codeHash($tenant->id, $user->id, $code),
            'weiter' => $this->cleanWeiter($weiter),
            'ip' => $ip,
            'expires_at' => now()->addMinutes($minuten ?? self::MINUTEN),
        ]);

        return ['url' => route('anmelden.token', ['token' => $plain]), 'code' => $code];
    }

    protected static function codeHash(int $tenantId, int $userId, string $code): string
    {
        return hash('sha256', $tenantId.'|'.$userId.'|'.preg_replace('~\D~', '', $code));
    }

    /**
     * Loest den Code zur Adresse ein (nur im Mandanten dieser Domain). Jeder falsche Versuch zaehlt auf allen
     * offenen Tokens der Person, nach fuenf ist der Code verbrannt. Gibt den Token zurueck oder null.
     */
    public function consumeCode(string $email, string $code): ?LoginToken
    {
        $user = User::where('email', Str::lower(trim($email)))->first();
        $code = preg_replace('~\D~', '', $code);
        if (! $user || strlen($code) !== 6) {
            return null;
        }
        $offen = LoginToken::query()->where('user_id', $user->id)->whereNotNull('code_hash')->whereNull('used_at')
            ->where('expires_at', '>', now())->lockForUpdate()->get()->filter->codeGueltig();
        $treffer = $offen->firstWhere('code_hash', self::codeHash($this->current->getOrFail()->id, $user->id, $code));
        if (! $treffer) {
            foreach ($offen as $t) {
                $t->increment('code_versuche');
            }

            return null;
        }
        $treffer->forceFill(['used_at' => now()])->save();

        return $treffer;
    }

    /**
     * Schickt den Link, wenn die Adresse zu einer Person mit Zugang zu diesem Mandanten gehoert.
     * Gibt still true/false zurueck, die Oberflaeche verraet nicht, ob die Adresse bekannt ist.
     */
    public function send(string $email, ?string $weiter = null, ?string $ip = null): bool
    {
        $user = User::where('email', Str::lower(trim($email)))->first();

        if (! $user || ! $user->hasAccessTo()) {
            return false;
        }

        $t = $this->erzeugen($user, $weiter, $ip);

        Mail::to($user->email, $user->name)->send(new MagicLinkMail($user, $t['url'], self::MINUTEN, $t['code']));

        return true;
    }

    /**
     * Loest den Token ein. Gibt die Person zurueck oder null, wenn der Link
     * unbekannt, abgelaufen, verbraucht oder fuer einen anderen Mandanten ist.
     */
    public function consume(string $plain): ?LoginToken
    {
        $token = LoginToken::query()
            ->where('token_hash', hash('sha256', $plain))
            ->lockForUpdate()
            ->first();

        if (! $token || ! $token->isValid()) {
            return null;
        }

        $token->forceFill(['used_at' => now()])->save();

        return $token;
    }

    /** Abgelaufene und verbrauchte Tokens aufraeumen (Scheduler). */
    public static function prune(): int
    {
        return LoginToken::withoutGlobalScopes()
            ->where(fn ($q) => $q->where('expires_at', '<', now()->subDay())->orWhereNotNull('used_at'))
            ->where('created_at', '<', now()->subDay())
            ->delete();
    }

    /** Nur relative Pfade als Ziel, keine fremden Adressen. */
    public static function cleanWeiter(?string $weiter): ?string
    {
        $weiter = trim((string) $weiter);

        if ($weiter === '' || ! str_starts_with($weiter, '/') || str_starts_with($weiter, '//')) {
            return null;
        }

        return $weiter;
    }

    public static function tenantOf(LoginToken $token): Tenant
    {
        return $token->tenant;
    }
}

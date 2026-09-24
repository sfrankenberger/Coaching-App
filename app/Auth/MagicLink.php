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
    public function create(User $user, ?string $weiter = null, ?string $ip = null): string
    {
        $tenant = $this->current->getOrFail();
        $plain = Str::random(48);

        LoginToken::create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $plain),
            'weiter' => $this->cleanWeiter($weiter),
            'ip' => $ip,
            'expires_at' => now()->addMinutes(self::MINUTEN),
        ]);

        return route('anmelden.token', ['token' => $plain]);
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

        $url = $this->create($user, $weiter, $ip);

        Mail::to($user->email, $user->name)->send(new MagicLinkMail($user, $url, self::MINUTEN));

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

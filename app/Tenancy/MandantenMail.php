<?php

namespace App\Tenancy;

use App\Models\Tenant;
use Illuminate\Support\Facades\Mail;

/**
 * Mailgun je Mandant: hat der Mandant eigene Zugangsdaten (settings.mail.mailgun_domain und mailgun_secret),
 * gehen seine Mails ueber sein Mailgun-Konto, sonst ueber das der Plattform aus der .env.
 * Wird beim Setzen des Mandanten angewendet und danach wieder zurueckgestellt.
 */
class MandantenMail
{
    protected static ?array $plattform = null;

    public static function anwenden(?Tenant $tenant): void
    {
        self::$plattform ??= ['default' => config('mail.default'), 'domain' => config('services.mailgun.domain'), 'secret' => config('services.mailgun.secret'), 'endpoint' => config('services.mailgun.endpoint')];
        $domain = trim((string) $tenant?->setting('mail.mailgun_domain'));
        $secret = trim((string) $tenant?->setting('mail.mailgun_secret'));

        if ($domain !== '' && $secret !== '') {
            $neu = ['mail.default' => 'mailgun', 'services.mailgun.domain' => $domain, 'services.mailgun.secret' => $secret, 'services.mailgun.endpoint' => $tenant->setting('mail.mailgun_endpoint') ?: 'api.eu.mailgun.net'];
        } else {
            $neu = ['mail.default' => self::$plattform['default'], 'services.mailgun.domain' => self::$plattform['domain'], 'services.mailgun.secret' => self::$plattform['secret'], 'services.mailgun.endpoint' => self::$plattform['endpoint']];
        }
        $anders = collect($neu)->contains(fn ($v, $k) => config($k) !== $v);
        config($neu);
        if ($anders) {
            // Der Mailer wird beim ersten Senden gebaut und behalten: bei anderen Zugangsdaten neu bauen
            Mail::purge('mailgun');
        }
    }

    public static function eigenes(?Tenant $tenant): bool
    {
        return filled($tenant?->setting('mail.mailgun_domain')) && filled($tenant?->setting('mail.mailgun_secret'));
    }
}

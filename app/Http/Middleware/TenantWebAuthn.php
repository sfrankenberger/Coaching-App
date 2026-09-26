<?php

namespace App\Http\Middleware;

use App\Tenancy\Branding;
use App\Tenancy\CurrentTenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Passkeys je Mandant: Relying Party aus den Einstellungen (settings.passkeys.rp_id),
 * sonst die aufgerufene Domain. Eine Haupt-Domain als RP-ID (z. B. leawernli.ch) erlaubt
 * Passkeys, die auf der Website registriert wurden, auch fuer die App-Subdomain.
 */
class TenantWebAuthn
{
    public function __construct(protected CurrentTenant $current, protected Branding $branding) {}

    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $this->current->get();
        $host = $request->getHost();
        $rpId = (string) ($tenant?->setting('passkeys.rp_id') ?: $host);
        $origins = array_unique(array_filter([$request->getSchemeAndHttpHost(), ...(array) ($tenant?->setting('passkeys.origins') ?: [])]));

        config([
            'webauthn.relying_party.id' => $rpId,
            'webauthn.relying_party.name' => $tenant ? $this->branding->appName() : config('app.name'),
            'webauthn.origins' => implode(',', $origins),
        ]);

        return $next($request);
    }
}

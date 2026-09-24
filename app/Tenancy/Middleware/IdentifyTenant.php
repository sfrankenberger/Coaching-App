<?php

namespace App\Tenancy\Middleware;

use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Tenancy\CurrentTenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ermittelt den Mandanten aus dem Hostnamen (Tabelle tenant_domains).
 */
class IdentifyTenant
{
    public function __construct(protected CurrentTenant $current) {}

    public function handle(Request $request, Closure $next): Response
    {
        $host = strtolower($request->getHost());

        if (in_array($host, config('tenancy.central_domains'), true)) {
            return $next($request);
        }

        $tenant = TenantDomain::query()
            ->where('domain', $host)
            ->with('tenant')
            ->first()?->tenant;

        if (! $tenant && ($slug = config('tenancy.fallback_slug'))) {
            $tenant = Tenant::where('slug', $slug)->first();
        }

        abort_unless($tenant && $tenant->is_active, 404);

        $this->current->set($tenant);
        app()->setLocale($tenant->locale ?? config('app.locale'));

        return $next($request);
    }
}

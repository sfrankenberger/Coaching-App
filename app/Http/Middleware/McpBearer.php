<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ohne gueltiges Token antwortet der MCP-Server mit 401 und sagt im Header WWW-Authenticate, wo die
 * OAuth-Beschreibung liegt (RFC 9728). So finden Claude und ChatGPT den Weg zur Anmeldung.
 */
class McpBearer
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user('sanctum')) {
            return response()->json(['jsonrpc' => '2.0', 'id' => null, 'error' => ['code' => -32001, 'message' => 'Anmeldung noetig']], 401)
                ->header('WWW-Authenticate', 'Bearer realm="mcp", resource_metadata="'.$request->getSchemeAndHttpHost().'/.well-known/oauth-protected-resource"');
        }
        auth()->shouldUse('sanctum');

        return $next($request);
    }
}

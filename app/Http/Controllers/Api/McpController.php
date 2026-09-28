<?php

namespace App\Http\Controllers\Api;

use App\Ai\Werkzeuge\Werkzeugkasten;
use App\Http\Controllers\Controller;
use App\Tenancy\Branding;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

/**
 * MCP-Server (Model Context Protocol) als "Streamable HTTP" ohne Sitzung: jede Anfrage ist ein
 * JSON-RPC-2.0-Aufruf mit Bearer-Token (Sanctum, Faehigkeit "mcp", nur Team). Claude, ChatGPT
 * oder ein anderer Assistent bekommt damit die Werkzeuge aus App\Ai\Werkzeuge. Kein SSE-Strom
 * (GET antwortet 405), die Antworten kommen sofort als JSON. Mandant kommt wie immer aus der Domain.
 */
class McpController extends Controller
{
    public const PROTOKOLL = '2025-06-18';

    public const VERSIONEN = ['2025-06-18', '2025-03-26', '2024-11-05'];

    public function __construct(protected Werkzeugkasten $kasten, protected Branding $branding) {}

    public function __invoke(Request $request): JsonResponse|Response
    {
        $user = $request->user();
        abort_unless($user && $user->canManageCurrentTenant() && $user->tokenCan('mcp'), 403, 'Dieser Schlüssel darf den MCP-Server nicht nutzen.');

        if ($request->isMethod('get')) {
            return response('', 405)->header('Allow', 'POST, DELETE');
        }
        if ($request->isMethod('delete')) {
            return response('', 200);
        }

        $body = $request->json()->all();
        if ($body === [] || ! is_array($body)) {
            return response()->json($this->fehler(null, -32700, 'Kein gültiges JSON.'), 400);
        }

        // Stapel (Liste von Aufrufen) oder ein einzelner Aufruf
        $stapel = array_is_list($body);
        $antworten = [];
        foreach ($stapel ? $body : [$body] as $aufruf) {
            $antwort = $this->bearbeiten(is_array($aufruf) ? $aufruf : [], $user);
            if ($antwort !== null) {
                $antworten[] = $antwort;
            }
        }
        if ($antworten === []) {
            return response('', 202);   // nur Benachrichtigungen
        }

        return response()->json($stapel ? $antworten : $antworten[0], 200, [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /** Ein JSON-RPC-Aufruf. Benachrichtigungen (ohne id) geben null zurueck. */
    protected function bearbeiten(array $aufruf, $user): ?array
    {
        $id = $aufruf['id'] ?? null;
        $methode = (string) ($aufruf['method'] ?? '');
        $params = is_array($aufruf['params'] ?? null) ? $aufruf['params'] : [];

        if (str_starts_with($methode, 'notifications/')) {
            return null;
        }
        if ($id === null) {
            return null;
        }

        try {
            $ergebnis = match ($methode) {
                'initialize' => $this->initialize($params),
                'ping' => new \stdClass,
                'tools/list' => ['tools' => $this->kasten->liste()],
                'tools/call' => $this->call($params, $user),
                'resources/list' => ['resources' => []],
                'prompts/list' => ['prompts' => []],
                default => throw new HttpException(404, 'Unbekannte Methode: '.$methode, null, [], -32601),
            };

            return ['jsonrpc' => '2.0', 'id' => $id, 'result' => $ergebnis];
        } catch (HttpException $e) {
            $code = $e->getCode() ?: ($e->getStatusCode() === 404 ? -32601 : -32602);

            return $this->fehler($id, $code, $e->getMessage() ?: 'Fehler '.$e->getStatusCode());
        } catch (Throwable $e) {
            report($e);

            return $this->fehler($id, -32603, 'Das hat nicht geklappt: '.$e->getMessage());
        }
    }

    protected function initialize(array $params): array
    {
        $gewuenscht = (string) ($params['protocolVersion'] ?? self::PROTOKOLL);

        return [
            'protocolVersion' => in_array($gewuenscht, self::VERSIONEN, true) ? $gewuenscht : self::PROTOKOLL,
            'capabilities' => ['tools' => ['listChanged' => false]],
            'serverInfo' => ['name' => $this->branding->appName(), 'version' => '1.0'],
            'instructions' => 'Du arbeitest im Namen von '.$this->branding->coachName().' in der App '.$this->branding->appName().'. '
                .'Sprich Menschen mit Du an, warm und kurz, Schweizer Schreibweise (kein ß). Zeiten sind Ortszeit des Betriebs. '
                .'Bevor du etwas änderst (Person anlegen, Zugang geben, Nachricht, Termin), nenne kurz, was du tun wirst. '
                .'Finde Personen zuerst mit personen_suchen und nutze dann die membership_id.',
        ];
    }

    protected function call(array $params, $user): array
    {
        $name = (string) ($params['name'] ?? '');
        $args = is_array($params['arguments'] ?? null) ? $params['arguments'] : [];
        abort_unless($this->kasten->finde($name), 404, 'Unbekanntes Werkzeug: '.$name);

        try {
            $text = $this->kasten->aufrufen($name, $args, $user);

            return ['content' => [['type' => 'text', 'text' => $text]], 'isError' => false];
        } catch (HttpException $e) {
            // Fachliche Fehler (niemand gefunden, mehrdeutig, Vergangenheit) gehen als Werkzeug-Fehler zurueck, nicht als Protokollfehler
            return ['content' => [['type' => 'text', 'text' => $e->getMessage()]], 'isError' => true];
        }
    }

    protected function fehler(mixed $id, int $code, string $text): array
    {
        return ['jsonrpc' => '2.0', 'id' => $id, 'error' => ['code' => $code, 'message' => $text]];
    }
}

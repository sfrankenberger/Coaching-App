<?php

namespace App\Ai;

use App\Models\Tenant;
use App\Tenancy\CurrentTenant;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Duenner Zugang zur Anthropic Messages API. Schluessel je Mandant
 * (settings.ai.anthropic_key) oder von der Plattform (config ai.anthropic_key).
 */
class Anthropic
{
    public const STIL = 'Sprache: Deutsch, Schweizer Rechtschreibung (immer ss statt scharfem S). Du-Form, warm und kurz. Verwende niemals lange Gedankenstriche, nur normale Bindestriche, Kommas, Punkte oder Klammern.';

    public function __construct(protected CurrentTenant $current) {}

    public static function keyFor(?Tenant $tenant): ?string
    {
        $key = (string) ($tenant?->setting('ai.anthropic_key') ?: config('ai.anthropic_key'));

        return $key !== '' ? $key : null;
    }

    public static function configured(?Tenant $tenant): bool
    {
        return self::keyFor($tenant) !== null;
    }

    public function model(): string
    {
        return (string) ($this->current->get()?->setting('ai.model') ?: config('ai.model'));
    }

    /** Eine Anfrage, eine Antwort. Gibt [text, model, tokens_in, tokens_out] zurueck. */
    public function text(string $prompt, ?string $system = null, ?int $maxTokens = null): array
    {
        $key = self::keyFor($this->current->get());
        if (! $key) {
            throw new RuntimeException('Kein Anthropic-Schlüssel hinterlegt (ANTHROPIC_API_KEY oder settings.ai.anthropic_key).');
        }
        $model = $this->model();
        $r = Http::timeout((int) config('ai.timeout'))
            ->withHeaders(['x-api-key' => $key, 'anthropic-version' => '2023-06-01'])
            ->post('https://api.anthropic.com/v1/messages', array_filter([
                'model' => $model,
                'max_tokens' => $maxTokens ?? (int) config('ai.max_tokens'),
                'system' => $system,
                'messages' => [['role' => 'user', 'content' => $prompt]],
            ]));
        if (! $r->successful()) {
            throw new RuntimeException('Anthropic: HTTP '.$r->status().' '.mb_substr((string) $r->body(), 0, 300));
        }
        $j = $r->json();
        $text = collect($j['content'] ?? [])->where('type', 'text')->pluck('text')->implode("\n");
        if (trim($text) === '') {
            throw new RuntimeException('Anthropic: leere Antwort');
        }

        return ['text' => $text, 'model' => $j['model'] ?? $model, 'tokens_in' => (int) ($j['usage']['input_tokens'] ?? 0), 'tokens_out' => (int) ($j['usage']['output_tokens'] ?? 0)];
    }

    /** Wie text(), aber die Antwort wird als JSON gelesen (Code-Zaeune werden entfernt). */
    public function json(string $prompt, ?string $system = null, ?int $maxTokens = null): array
    {
        $r = $this->text($prompt, $system, $maxTokens);
        $clean = trim(preg_replace('/^```(?:json)?\s*|\s*```$/m', '', trim($r['text'])));
        $data = json_decode($clean, true);
        if (! is_array($data) && preg_match('~\{.*\}~s', $clean, $m)) {
            $data = json_decode($m[0], true);
        }
        if (! is_array($data)) {
            throw new RuntimeException('Anthropic: Antwort ist kein JSON: '.mb_substr($r['text'], 0, 200));
        }
        $r['data'] = $data;

        return $r;
    }
}

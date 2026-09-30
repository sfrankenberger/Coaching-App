<?php

namespace App\Models;

use App\Support\Protokoll\Protokolliert;
use App\Support\Suche;
use App\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Laravel\Scout\Searchable;

/**
 * Ein Eintrag im Wissensspeicher (Second Brain): ein Gedanke, eine Regel, ein Fakt.
 * Der Assistent nimmt passende Eintraege in seine Antwort, die Werkzeuge (MCP) lesen und schreiben sie.
 */
class Wissen extends Model
{
    use BelongsToTenant, Searchable;
    use Protokolliert;

    /** Inhalt bleibt privat, im Verlauf steht nur, dass sich etwas geaendert hat. */
    protected static array $protokollSensibel = ['body'];

    protected $table = 'wissen';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['tags' => 'array'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function toSearchableArray(): array
    {
        return ['title' => $this->title, 'body' => Suche::text($this->body)];
    }

    /** Einfache Suche in Titel, Text und Schlagworten (Datenbank, ohne Scout-Umweg). */
    public function scopeSuche(Builder $q, string $begriff): Builder
    {
        $woerter = collect(preg_split('~\s+~u', mb_strtolower(trim($begriff))))->filter(fn ($w) => mb_strlen($w) >= 3)->take(6);

        return $q->where(function (Builder $q) use ($woerter, $begriff) {
            $q->where('title', 'like', '%'.$begriff.'%')->orWhere('body', 'like', '%'.$begriff.'%');
            foreach ($woerter as $w) {
                $q->orWhere('body', 'like', '%'.$w.'%')->orWhere('tags', 'like', '%'.$w.'%');
            }
        });
    }

    public static function merken(?User $von, string $body, ?string $title = null, string|array|null $tags = null, string $source = 'app'): self
    {
        $tags = is_string($tags) ? preg_split('~\s*[,;]\s*~u', trim($tags)) : ($tags ?? []);
        $tags = collect($tags)->map(fn ($t) => trim((string) $t))->filter()->unique()->values()->all();

        return self::create([
            'user_id' => $von?->id,
            'title' => filled($title) ? trim($title) : Str::limit(Suche::text($body), 60, ''),
            'body' => trim($body),
            'tags' => $tags ?: null,
            'source' => $source,
        ]);
    }

    /** Fuer den Assistenten: passende Eintraege als Faktenzeilen. */
    public static function fakten(string $frage, int $max = 6): array
    {
        $liste = self::query()->suche($frage)->latest()->limit($max)->get();
        if ($liste->isEmpty()) {
            $liste = self::query()->latest()->limit(3)->get();
        }

        return $liste->map(fn (self $w) => ($w->title ? $w->title.': ' : '').Str::limit(Suche::text($w->body), 400))->all();
    }
}

<?php

namespace App\Ai\Werkzeuge;

use App\Models\User;
use Illuminate\Support\Collection;

/** Alle Werkzeuge, die ein Assistent in der App nutzen darf (MCP-Server, spaeter Leas Assistent). */
class Werkzeugkasten
{
    /** @var array<int, class-string<Werkzeug>> */
    public const WERKZEUGE = [
        HeuteListe::class,
        PersonenSuchen::class,
        PersonFakten::class,
        PersonAnlegen::class,
        ZugangGeben::class,
        AngeboteListe::class,
        NachrichtSenden::class,
        AufgabeGeben::class,
        TerminAnlegen::class,
        ZeitenVorschlagen::class,
        TermineListe::class,
        LageAmpel::class,
        NotizSchreiben::class,
        InhalteSuchen::class,
        WissenSuchen::class,
        WissenMerken::class,
        KontakteSuchen::class,
        KontaktTaggen::class,
        NewsletterListe::class,
        NewsletterAnlegen::class,
        NewsletterSenden::class,
        ImpulsAnlegen::class,
        RundnachrichtSenden::class,
    ];

    /** @return Collection<string, Werkzeug> */
    public function alle(): Collection
    {
        return collect(self::WERKZEUGE)->map(fn ($k) => app($k))->keyBy(fn (Werkzeug $w) => $w->name());
    }

    public function finde(string $name): ?Werkzeug
    {
        return $this->alle()->get($name);
    }

    /** Liste fuer tools/list im MCP. */
    public function liste(): array
    {
        return $this->alle()->values()->map(fn (Werkzeug $w) => [
            'name' => $w->name(),
            'description' => $w->beschreibung(),
            'inputSchema' => $w->schema(),
            'annotations' => ['readOnlyHint' => ! $w->schreibt(), 'destructiveHint' => false],
        ])->all();
    }

    /** Werkzeug ausfuehren, Ergebnis als Text (JSON oder Satz). */
    public function aufrufen(string $name, array $args, User $von): string
    {
        $w = $this->finde($name);
        abort_unless($w, 404, 'Unbekanntes Werkzeug: '.$name);
        $ergebnis = $w->ausfuehren($args, $von);

        return is_string($ergebnis) ? $ergebnis : json_encode($ergebnis, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }
}

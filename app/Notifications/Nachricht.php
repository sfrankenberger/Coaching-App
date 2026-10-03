<?php

namespace App\Notifications;

/** Inhalt einer Benachrichtigung, kanalunabhaengig. */
class Nachricht
{
    public function __construct(
        public string $titel,
        public string $text,
        public ?string $url = null,
        public string $anlass = 'system',
        public ?string $tag = null,
        public bool $mailWennKeinPush = true,
        public ?string $mailBetreff = null,
        public ?string $knopf = null,
        public ?string $html = null,          // nur Mail: zusaetzlicher Inhalt, z. B. die Zusammenfassung
        public bool $mailImmer = false,       // Mail auch an Personen mit Push (z. B. Aufzeichnung mit Zusammenfassung)
        public bool $inApp = true,            // auch als Mitteilung in der App (Glocke)
        public ?array $anhang = null,         // nur Mail: ['name' => 'termin.ics', 'inhalt' => '...', 'typ' => 'text/calendar']
        public ?array $liste = null,          // nur Mail: Karten mit je einem Link, [['titel','text','herkunft','url','knopf'], ...]
        public ?array $bloecke = null,        // nur Mail: Bausteine (App\Newsletter\Bausteine) statt des Textes, Push und App zeigen weiter den Text
    ) {
        $this->tag ??= $anlass;
    }

    public function toArray(): array
    {
        return ['titel' => $this->titel, 'text' => $this->text, 'url' => $this->url, 'anlass' => $this->anlass, 'tag' => $this->tag];
    }
}

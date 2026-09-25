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
    ) {
        $this->tag ??= $anlass;
    }

    public function toArray(): array
    {
        return ['titel' => $this->titel, 'text' => $this->text, 'url' => $this->url, 'anlass' => $this->anlass, 'tag' => $this->tag];
    }
}

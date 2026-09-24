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
    ) {
        $this->tag ??= $anlass;
    }

    public function toArray(): array
    {
        return ['titel' => $this->titel, 'text' => $this->text, 'url' => $this->url, 'anlass' => $this->anlass, 'tag' => $this->tag];
    }
}

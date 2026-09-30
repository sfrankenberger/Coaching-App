<?php

namespace App\Support\Backup;

use Carbon\CarbonImmutable;

/** Ein gesicherter Stand, wie ihn die Server-Schnittstelle meldet. */
final class Stand
{
    public const QUELLEN = ['local' => 'Server', 'remote' => 'HiDrive'];

    public function __construct(
        public readonly string $id,
        public readonly CarbonImmutable $zeit,
        public readonly string $quelle,
        public readonly bool $vorWiederherstellung = false,
    ) {}

    /** Baut einen Stand aus einer Zeile der Schnittstelle, tolerant gegenueber Feldnamen. */
    public static function ausZeile(array $zeile): ?self
    {
        $id = $zeile['id'] ?? $zeile['short_id'] ?? null;
        $zeit = $zeile['time'] ?? $zeile['zeit'] ?? null;
        if (! is_string($id) || ! is_string($zeit) || ! preg_match('/^[0-9a-f]{6,64}$/i', $id)) {
            return null;
        }

        try {
            $zeit = CarbonImmutable::parse($zeit);
        } catch (\Throwable) {
            return null;
        }

        $quelle = strtolower((string) ($zeile['source'] ?? $zeile['repo'] ?? $zeile['quelle'] ?? 'local'));
        $quelle = $quelle === 'remote' || $quelle === 'hidrive' ? 'remote' : 'local';

        $tags = array_map('strval', (array) ($zeile['tags'] ?? []));
        $vor = (bool) ($zeile['pre_restore'] ?? $zeile['vor_wiederherstellung'] ?? false)
            || (bool) array_intersect(['pre-restore', 'pre_restore', 'vor-wiederherstellung'], $tags);

        return new self(strtolower($id), $zeit, $quelle, $vor);
    }

    public function quelleName(): string
    {
        return self::QUELLEN[$this->quelle] ?? $this->quelle;
    }
}

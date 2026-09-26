<?php

namespace App\Recordings;

use Throwable;

/** Gemeinsam fuer Wache (Termine) und MaterialVideo: Vimeo-Nummer finden, Abschrift holen, Fehler nur melden. */
trait HoltAbschrift
{
    /** Erste Vimeo-Nummer aus mehreren Quellen (ID-Feld, Links, Text). */
    protected static function vimeoNummer(?string ...$quellen): ?string
    {
        foreach ($quellen as $q) {
            if ($q && preg_match('~^\d+$~', $q)) {
                return $q;
            }
            if ($n = Vimeo::nummerAus($q)) {
                return $n;
            }
        }

        return null;
    }

    /** Abschrift aus der Textspur, null wenn (noch) keine da ist oder Vimeo nicht antwortet. */
    protected function abschriftHolen(?string $vimeoId): ?string
    {
        if (! $vimeoId) {
            return null;
        }
        try {
            return $this->vimeo->abschrift($vimeoId) ?: null;
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }
}

<?php

namespace App\Coach;

use App\Models\User;

/**
 * Drei Sichten auf dieselbe App: Teilnehmerin (einfach), Arbeitsplatz (Coachin und Team im Alltag,
 * in derselben Huelle) und Verwaltung (Filament unter /coach, zum Einrichten). Das Team schaltet
 * zwischen Arbeitsplatz und "wie eine Teilnehmerin" um, gemerkt in memberships.settings.ansicht.
 */
class Ansicht
{
    public const ARBEITSPLATZ = 'arbeitsplatz';

    public const TEILNEHMER = 'teilnehmer';

    /** Sieht die Person gerade den Arbeitsplatz? Nur fuer Verwaltende, Vorgabe ja. */
    public static function arbeitsplatz(?User $user): bool
    {
        if (! $user || ! $user->canManageCurrentTenant()) {
            return false;
        }

        return ($user->membershipIn()?->setting('ansicht') ?? self::ARBEITSPLATZ) === self::ARBEITSPLATZ;
    }

    public static function setzen(User $user, string $ansicht): void
    {
        $m = $user->membershipIn();
        if (! $m) {
            return;
        }
        $ansicht = $ansicht === self::TEILNEHMER ? self::TEILNEHMER : self::ARBEITSPLATZ;
        $m->forceFill(['settings' => array_merge(is_array($m->settings) ? $m->settings : [], ['ansicht' => $ansicht])])->save();
    }
}

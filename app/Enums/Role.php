<?php

namespace App\Enums;

/**
 * Rolle einer Person innerhalb eines Mandanten (nicht plattformweit).
 */
enum Role: string
{
    case Owner = 'owner';       // die Coachin selbst (Lea)
    case Team = 'team';         // Assistenz, Redaktion (Andrea)
    case Client = 'client';     // 1:1-Kundin
    case Member = 'member';     // Kurs- oder Club-Teilnehmerin
    case Guest = 'guest';       // Gratis-Einstieg, Interessentin

    public function canManage(): bool
    {
        return in_array($this, [self::Owner, self::Team], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Inhaberin',
            self::Team => 'Team',
            self::Client => '1:1-Kundin',
            self::Member => 'Teilnehmerin',
            self::Guest => 'Gast',
        };
    }
}

<?php

namespace App\Filament\Coach\Pages;

use App\Enums\Role;
use App\Shop\Zahlen as Auswertung;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

/** Umsatz und offene Posten fuer die Inhaberin (wie lea-zahlen.php), aus den Verkaeufen der App. */
class Zahlen extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static ?string $navigationLabel = 'Zahlen';

    protected static ?string $title = 'Zahlen';

    protected static ?int $navigationSort = 62;

    protected string $view = 'filament.coach.zahlen';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return (bool) ($user?->is_platform_admin || $user?->roleIn() === Role::Owner);
    }

    public function zahlen(): array
    {
        return app(Auswertung::class)->uebersicht();
    }
}

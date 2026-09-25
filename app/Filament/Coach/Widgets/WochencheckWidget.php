<?php

namespace App\Filament\Coach\Widgets;

use App\Coach\Wochencheck;
use Filament\Widgets\Widget;

/** Wochenpruefung fuers Team: was fehlt in dieser und der naechsten Kurswoche. */
class WochencheckWidget extends Widget
{
    protected static bool $isLazy = false;

    protected static ?int $sort = 2;

    protected string $view = 'filament.coach.widgets.wochencheck';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return app(Wochencheck::class)->programme()->isNotEmpty() || app(Wochencheck::class)->haken() !== [];
    }

    public function haken(string $key, bool $an): void
    {
        app(Wochencheck::class)->abhaken($key, $an, auth()->user());
    }

    protected function getViewData(): array
    {
        $w = app(Wochencheck::class);

        return ['programme' => $w->programme(), 'haken' => $w->haken(), 'kw' => (int) substr($w->kw(), 5)];
    }
}

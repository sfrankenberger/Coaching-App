<?php

namespace App\Filament\Coach\Widgets;

use App\Coach\Neues;
use Filament\Widgets\Widget;

/** Was in den letzten sieben Tagen von den Personen kam, und was ansteht. */
class NeuesWidget extends Widget
{
    protected static bool $isLazy = false;

    protected static ?int $sort = 2;

    protected string $view = 'filament.coach.widgets.neues';

    protected int|string|array $columnSpan = 'full';

    protected function getViewData(): array
    {
        $neues = app(Neues::class);

        return ['zeilen' => $neues->zeilen(), 'termine' => $neues->termine()];
    }
}

<?php

namespace App\Filament\Coach\Widgets;

use App\Chat\Chat;
use App\Coach\Lage;
use App\Filament\Coach\Resources\Memberships\MembershipResource;
use Filament\Widgets\Widget;

/** Ampel ueber alle begleiteten Personen: wer wartet, wer ist still, wo hakt es. */
class AmpelWidget extends Widget
{
    protected static bool $isLazy = false;

    protected static ?int $sort = 1;

    protected string $view = 'filament.coach.widgets.ampel';

    protected int|string|array $columnSpan = 'full';

    protected function getViewData(): array
    {
        $alle = app(Lage::class)->alle();
        $chat = app(Chat::class);

        return [
            'zeilen' => $alle->where('stufe', '>', 1)->take(12)->map(fn ($z) => $z + [
                'dossier' => MembershipResource::getUrl('dossier', ['record' => $z['membership']]),
                'nachfragen' => route('gespraech.show', ['gespraech' => $chat->directFor($z['user']), 'entwurf' => Lage::entwurf($z['entwurf'], $z['user']->vorname())]),
            ]),
            'wartend' => $alle->whereNotNull('wartet')->count(),
            'gruen' => $alle->where('stufe', 1)->count(),
            'gesamt' => $alle->count(),
        ];
    }
}

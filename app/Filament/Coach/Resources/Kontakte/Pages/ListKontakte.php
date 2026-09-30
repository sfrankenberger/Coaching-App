<?php

namespace App\Filament\Coach\Resources\Kontakte\Pages;

use App\Filament\Coach\Resources\Kontakte\KontaktResource;
use App\Models\Kontakt;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListKontakte extends ListRecords
{
    protected static string $resource = KontaktResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Kontakt anlegen')];
    }

    public function getSubheading(): ?string
    {
        $n = Kontakt::bestaetigt()->count();

        return $n.' bestätigte Kontakte. Anmeldeformular für die Website: [app_anmelden tag="newsletter"] oder '.route('newsletter.anmelden');
    }
}

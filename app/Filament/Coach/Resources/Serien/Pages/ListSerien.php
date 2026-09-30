<?php

namespace App\Filament\Coach\Resources\Serien\Pages;

use App\Filament\Coach\Resources\Serien\SerieResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSerien extends ListRecords
{
    protected static string $resource = SerieResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Serie anlegen')];
    }
}

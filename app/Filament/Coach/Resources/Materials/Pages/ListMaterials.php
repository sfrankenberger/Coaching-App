<?php

namespace App\Filament\Coach\Resources\Materials\Pages;

use App\Filament\Coach\Resources\Materials\MaterialResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMaterials extends ListRecords
{
    protected static string $resource = MaterialResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Material anlegen')];
    }
}

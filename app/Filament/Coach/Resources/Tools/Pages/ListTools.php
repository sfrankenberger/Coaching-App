<?php

namespace App\Filament\Coach\Resources\Tools\Pages;

use App\Filament\Coach\Resources\Tools\ToolResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTools extends ListRecords
{
    protected static string $resource = ToolResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Werkzeug anlegen')];
    }
}

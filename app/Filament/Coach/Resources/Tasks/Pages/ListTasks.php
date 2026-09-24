<?php

namespace App\Filament\Coach\Resources\Tasks\Pages;

use App\Filament\Coach\Resources\Tasks\TaskResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTasks extends ListRecords
{
    protected static string $resource = TaskResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Aufgabe geben')];
    }
}

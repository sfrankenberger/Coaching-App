<?php

namespace App\Filament\Coach\Resources\Programs\Pages;

use App\Filament\Coach\Resources\Programs\ProgramResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

/** Kurse, Club, Arbeitsbuch und Hybrid: das, was die Coachin baut. 1:1-Begleitungen stehen im Dossier der Person, nicht hier. */
class ListPrograms extends ListRecords
{
    protected static string $resource = ProgramResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Programm anlegen'),
        ];
    }
}

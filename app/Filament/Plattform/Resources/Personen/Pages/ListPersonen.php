<?php

namespace App\Filament\Plattform\Resources\Personen\Pages;

use App\Filament\Plattform\Resources\Personen\PersonResource;
use Filament\Resources\Pages\ListRecords;

class ListPersonen extends ListRecords
{
    protected static string $resource = PersonResource::class;

    protected static ?string $title = 'Personen';
}

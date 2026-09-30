<?php

namespace App\Filament\Coach\Resources\Protokoll\Pages;

use App\Filament\Coach\Resources\Protokoll\ProtokollResource;
use Filament\Resources\Pages\ListRecords;

class ListProtokoll extends ListRecords
{
    protected static string $resource = ProtokollResource::class;

    protected static ?string $title = 'Verlauf';
}

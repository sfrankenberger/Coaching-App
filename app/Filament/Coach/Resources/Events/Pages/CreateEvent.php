<?php

namespace App\Filament\Coach\Resources\Events\Pages;

use App\Filament\Coach\Resources\Events\EventResource;
use Filament\Resources\Pages\CreateRecord;

class CreateEvent extends CreateRecord
{
    protected static string $resource = EventResource::class;
}

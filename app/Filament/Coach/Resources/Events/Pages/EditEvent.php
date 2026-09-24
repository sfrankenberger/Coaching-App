<?php

namespace App\Filament\Coach\Resources\Events\Pages;

use App\Filament\Coach\Resources\Events\EventResource;
use Filament\Resources\Pages\EditRecord;

class EditEvent extends EditRecord
{
    protected static string $resource = EventResource::class;
}

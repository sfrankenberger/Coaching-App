<?php

namespace App\Filament\Coach\Resources\BookingTypes\Pages;

use App\Filament\Coach\Resources\BookingTypes\BookingTypeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageBookingTypes extends ManageRecords
{
    protected static string $resource = BookingTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}

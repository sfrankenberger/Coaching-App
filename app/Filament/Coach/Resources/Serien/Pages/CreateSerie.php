<?php

namespace App\Filament\Coach\Resources\Serien\Pages;

use App\Filament\Coach\Resources\Serien\SerieResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSerie extends CreateRecord
{
    protected static string $resource = SerieResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return SerieResource::ausBausteinen($data);
    }
}

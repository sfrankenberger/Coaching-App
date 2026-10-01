<?php

namespace App\Filament\Coach\Resources\Serien\Pages;

use App\Filament\Coach\Resources\Serien\SerieResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditSerie extends EditRecord
{
    protected static string $resource = SerieResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return SerieResource::altZuBausteinen($data);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return SerieResource::ausBausteinen($data);
    }
}

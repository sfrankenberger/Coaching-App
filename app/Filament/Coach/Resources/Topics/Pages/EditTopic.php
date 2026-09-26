<?php

namespace App\Filament\Coach\Resources\Topics\Pages;

use App\Filament\Coach\Resources\Topics\TopicResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTopic extends EditRecord
{
    protected static string $resource = TopicResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}

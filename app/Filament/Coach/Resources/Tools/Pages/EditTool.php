<?php

namespace App\Filament\Coach\Resources\Tools\Pages;

use App\Filament\Coach\Resources\Tools\ToolResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTool extends EditRecord
{
    protected static string $resource = ToolResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}

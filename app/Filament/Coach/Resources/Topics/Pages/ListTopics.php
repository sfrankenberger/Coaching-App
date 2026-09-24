<?php

namespace App\Filament\Coach\Resources\Topics\Pages;

use App\Filament\Coach\Resources\Topics\TopicResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTopics extends ListRecords
{
    protected static string $resource = TopicResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Thema anlegen')];
    }
}

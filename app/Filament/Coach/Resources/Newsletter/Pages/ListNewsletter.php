<?php

namespace App\Filament\Coach\Resources\Newsletter\Pages;

use App\Filament\Coach\Resources\Newsletter\NewsletterResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListNewsletter extends ListRecords
{
    protected static string $resource = NewsletterResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Newsletter schreiben')];
    }
}

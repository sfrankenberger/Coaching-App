<?php

namespace App\Filament\Coach\Resources\Newsletter\Pages;

use App\Filament\Coach\Resources\Newsletter\NewsletterResource;
use App\Models\Kontakt;
use Filament\Resources\Pages\CreateRecord;

class CreateNewsletter extends CreateRecord
{
    protected static string $resource = NewsletterResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();
        $data = NewsletterResource::ausBausteinen($data);
        $data['tags'] = array_values(array_unique(array_map([Kontakt::class, 'tagSauber'], (array) ($data['tags'] ?? []))));
        $data['status'] = filled($data['geplant_at'] ?? null) ? 'geplant' : 'entwurf';

        return $data;
    }
}

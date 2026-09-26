<?php

namespace App\Filament\Coach\Resources\Posts\Pages;

use App\Filament\Coach\Resources\Posts\PostResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePost extends CreateRecord
{
    protected static string $resource = PostResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['author_id'] = auth()->id();
        $data['source'] = 'app';

        return $data;
    }
}

<?php

namespace App\Filament\Coach\Resources\Podcast\Pages;

use App\Filament\Coach\Resources\Podcast\PodcastEpisodeResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreatePodcastEpisode extends CreateRecord
{
    protected static string $resource = PodcastEpisodeResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['guid'] = $data['guid'] ?? 'app-'.Str::uuid();

        return $data;
    }
}

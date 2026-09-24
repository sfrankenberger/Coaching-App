<?php

namespace App\Filament\Coach\Resources\Podcast\Pages;

use App\Filament\Coach\Resources\Podcast\PodcastEpisodeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPodcastEpisodes extends ListRecords
{
    protected static string $resource = PodcastEpisodeResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Folge anlegen')];
    }
}

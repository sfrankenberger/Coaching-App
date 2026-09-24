<?php

namespace App\Filament\Coach\Resources\Podcast\Pages;

use App\Filament\Coach\Resources\Podcast\PodcastEpisodeResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPodcastEpisode extends EditRecord
{
    protected static string $resource = PodcastEpisodeResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}

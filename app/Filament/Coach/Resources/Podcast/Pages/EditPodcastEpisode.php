<?php

namespace App\Filament\Coach\Resources\Podcast\Pages;

use App\Ai\Anthropic;
use App\Filament\Coach\Resources\Podcast\PodcastEpisodeResource;
use App\Jobs\PrepareEpisode;
use App\Jobs\ProfileContent;
use App\Tenancy\CurrentTenant;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditPodcastEpisode extends EditRecord
{
    protected static string $resource = PodcastEpisodeResource::class;

    protected function getHeaderActions(): array
    {
        $tenant = app(CurrentTenant::class)->get();

        return [
            Action::make('aufbereiten')->label('Aufbereiten (KI)')->icon('heroicon-o-sparkles')
                ->visible(fn () => Anthropic::configured($tenant))->disabled(fn () => blank($this->record->transcript))
                ->tooltip(fn () => blank($this->record->transcript) ? 'Erst die Abschrift eintragen.' : null)
                ->requiresConfirmation()->modalHeading('Kapitel, Fragen und Zusammenfassung schreiben lassen?')
                ->action(function () {
                    PrepareEpisode::dispatch(app(CurrentTenant::class)->id(), $this->record->id, auth()->id());
                    Notification::make()->title('Läuft im Hintergrund. Lade die Seite in ein paar Minuten neu.')->success()->send();
                }),
            Action::make('themen')->label('Themen zuordnen (KI)')->icon('heroicon-o-tag')
                ->visible(fn () => Anthropic::configured($tenant))
                ->action(function () {
                    ProfileContent::dispatch(app(CurrentTenant::class)->id(), 'episode', $this->record->id);
                    Notification::make()->title('Läuft im Hintergrund.')->success()->send();
                }),
            DeleteAction::make(),
        ];
    }
}

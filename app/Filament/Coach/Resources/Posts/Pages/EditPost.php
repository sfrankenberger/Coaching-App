<?php

namespace App\Filament\Coach\Resources\Posts\Pages;

use App\Ai\Anthropic;
use App\Filament\Coach\Resources\Posts\PostResource;
use App\Jobs\ProfileContent;
use App\Tenancy\CurrentTenant;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditPost extends EditRecord
{
    protected static string $resource = PostResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('themen')->label('Themen zuordnen (KI)')->icon('heroicon-o-tag')
                ->visible(fn () => Anthropic::configured(app(CurrentTenant::class)->get()))
                ->action(function () {
                    ProfileContent::dispatch(app(CurrentTenant::class)->id(), 'post', $this->record->id);
                    Notification::make()->title('Läuft im Hintergrund. Die Themen erscheinen nach dem nächsten Laden.')->success()->send();
                }),
            DeleteAction::make(),
        ];
    }
}

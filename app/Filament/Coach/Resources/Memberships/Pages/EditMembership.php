<?php

namespace App\Filament\Coach\Resources\Memberships\Pages;

use App\Filament\Coach\Resources\Memberships\MembershipResource;
use App\Shop\Zugang;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditMembership extends EditRecord
{
    protected static string $resource = MembershipResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('einladen')->label('Einladung schicken')->icon('heroicon-o-envelope')->requiresConfirmation()
                ->modalHeading('Willkommensmail mit Anmeldelink schicken?')->modalDescription('Der Link gilt sieben Tage. Danach geht es mit dem Magic Link weiter.')
                ->action(function () {
                    app(Zugang::class)->welcome($this->record->user);
                    Notification::make()->title('Einladung an '.$this->record->user->email.' geschickt')->success()->send();
                }),
            DeleteAction::make()->label('Zugang entfernen'),
        ];
    }
}

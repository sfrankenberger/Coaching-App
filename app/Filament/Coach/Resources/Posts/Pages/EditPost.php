<?php

namespace App\Filament\Coach\Resources\Posts\Pages;

use App\Ai\Anthropic;
use App\Filament\Coach\Resources\Newsletter\NewsletterResource;
use App\Filament\Coach\Resources\Posts\PostResource;
use App\Jobs\ProfileContent;
use App\Models\Newsletter;
use App\Tenancy\CurrentTenant;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Str;

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
            Action::make('newsletter')->label('Als Newsletter vorbereiten')->icon('heroicon-o-envelope')->color('gray')
                ->requiresConfirmation()->modalHeading('Newsletter aus diesem Beitrag')
                ->modalDescription('Legt einen Newsletter-Entwurf mit Bild, Titel, Kurztext und Knopf «Weiterlesen» an. Empfängerinnen und Text passt du dort an, gesendet wird erst auf Knopfdruck.')
                ->modalSubmitActionLabel('Entwurf anlegen')
                ->action(function () {
                    $n = Newsletter::create([
                        'created_by' => auth()->id(),
                        'betreff' => $this->record->title,
                        'titel' => $this->record->title,
                        'vorschautext' => Str::limit($this->record->excerptText(140), 140, ''),
                        'text' => trim(($this->record->excerptText(600) ?: '')."\n\nWeiterlesen: ".($this->record->url ?: route('impulse.show', $this->record))),
                        'bild_url' => $this->record->image_url,
                        'knopf_text' => 'Weiterlesen',
                        'knopf_url' => $this->record->url ?: route('impulse.show', $this->record),
                        'tags' => ['newsletter'],
                        'status' => 'entwurf',
                    ]);
                    $this->redirect(NewsletterResource::getUrl('edit', ['record' => $n]));
                }),
            DeleteAction::make(),
        ];
    }
}

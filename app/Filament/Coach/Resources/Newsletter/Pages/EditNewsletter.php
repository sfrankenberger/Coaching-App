<?php

namespace App\Filament\Coach\Resources\Newsletter\Pages;

use App\Filament\Coach\Resources\Newsletter\NewsletterResource;
use App\Models\Kontakt;
use App\Newsletter\Bausteine;
use App\Newsletter\Versand;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

/** Test an bis zu fuenf Adressen, Senden mit Rueckfrage ("geht raus an X, nicht rueckgaengig"). */
class EditNewsletter extends EditRecord
{
    protected static string $resource = NewsletterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('test')->label('Test schicken')->icon('heroicon-o-beaker')->color('gray')
                ->schema([TextInput::make('adressen')->label('An (bis zu 5, mit Komma)')->required()->default(fn () => implode(', ', $this->record->settings['testadressen'] ?? [auth()->user()->email]))->helperText('Ungespeicherte Änderungen zuerst speichern, der Test nimmt den gespeicherten Stand.')])
                ->action(function (array $data) {
                    $n = app(Versand::class)->test($this->record->fresh(), explode(',', $data['adressen']));
                    Notification::make()->title($n ? "Test an $n Adresse".($n === 1 ? '' : 'n').' geschickt' : 'Keine gültige Adresse')->status($n ? 'success' : 'danger')->send();
                }),
            Action::make('senden')->label('Jetzt senden')->icon('heroicon-o-paper-airplane')
                ->visible(fn () => $this->record->istEntwurf())
                ->requiresConfirmation()->modalHeading('Newsletter senden?')
                ->modalDescription(fn () => 'Geht raus an '.$this->record->empfaengerQuery()->count().' bestätigte Kontakte'.($this->record->tags ? ' mit Tag '.implode(', ', $this->record->tags) : '').', in Wellen von '.Versand::WELLE.' pro Minute. Das lässt sich nicht rückgängig machen. Ungespeicherte Änderungen gehen nicht mit.')
                ->modalSubmitActionLabel('Ja, jetzt senden')
                ->action(function () {
                    $n = app(Versand::class)->starten($this->record->fresh());
                    Notification::make()->title($n ? "Versand läuft: $n Empfängerinnen" : 'Keine Empfängerinnen, nichts gesendet')->status($n ? 'success' : 'warning')->send();
                    $this->redirect(NewsletterResource::getUrl('index'));
                }),
            Action::make('vorschau')->label('Vorschau im Browser')->icon('heroicon-o-eye')->color('gray')->url(fn () => route('newsletter.vorschau', $this->record), shouldOpenInNewTab: true),
            DeleteAction::make()->visible(fn () => $this->record->istEntwurf()),
        ];
    }

    /** Entwuerfe aus der Zeit vor dem Baukasten (und aus den KI-Werkzeugen): Bild, Headline, Text, Knopf werden Bausteine. */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        if (empty($data['bloecke'])) {
            $data['bloecke'] = Bausteine::ausAlt($data['bild_url'] ?? null, $data['titel'] ?? null, $data['text'] ?? null, $data['knopf_text'] ?? null, $data['knopf_url'] ?? null);
        }

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (! $this->record->istEntwurf()) {
            return [];   // gesendet: nichts mehr aendern
        }
        $data = NewsletterResource::ausBausteinen($data);
        $data['tags'] = array_values(array_unique(array_map([Kontakt::class, 'tagSauber'], (array) ($data['tags'] ?? []))));
        $data['status'] = filled($data['geplant_at'] ?? null) ? 'geplant' : 'entwurf';

        return $data;
    }
}

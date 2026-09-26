<?php

namespace App\Filament\Coach\Resources\Materials\Pages;

use App\Filament\Coach\Resources\Materials\MaterialResource;
use App\Models\Resourceable;
use App\Observers\ResourceableObserver;
use App\Recordings\MaterialVideo;
use App\Recordings\Vimeo;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditMaterial extends EditRecord
{
    protected static string $resource = MaterialResource::class;

    protected array $vorher = [];

    protected function getHeaderActions(): array
    {
        return [
            Action::make('video')->label('Video aufbereiten')->icon('heroicon-o-film')->color('gray')
                ->visible(fn () => app(Vimeo::class)->konfiguriert() && MaterialVideo::vimeoId($this->record))
                ->requiresConfirmation()->modalHeading('Dauer, Bild, Abschrift und Zusammenfassung von Vimeo holen?')
                ->modalDescription('Eine vorhandene Zusammenfassung wird neu geschrieben. Dauert etwa eine Minute.')
                ->action(function () {
                    $stand = app(MaterialVideo::class)->aufbereiten($this->record, true);
                    $this->record->refresh();
                    $this->fillForm();
                    Notification::make()->title(\App\Models\Resource::PREPARE_STATUS[$stand] ?? $stand)
                        ->{$stand === 'bereit' ? 'success' : 'warning'}()->send();
                }),
        ];
    }

    protected function beforeSave(): void
    {
        $this->vorher = $this->record->users()->pluck('users.id')->all();
    }

    /** Neu fuer Personen freigegebenes Material: die Person erfaehrt es. */
    protected function afterSave(): void
    {
        $vorher = $this->vorher ?? [];
        $jetzt = $this->record->users()->pluck('users.id')->all();
        foreach (array_diff($jetzt, $vorher) as $uid) {
            // Filament haengt ueber die Pivot-Tabelle an, ohne Modell-Ereignis: darum hier explizit
            $link = Resourceable::where('resource_id', $this->record->id)->where('resourceable_type', 'user')->where('resourceable_id', $uid)->first();
            if ($link) {
                app(ResourceableObserver::class)->created($link->fill(['shared_by' => $link->shared_by ?: auth()->id()]));
            }
        }
    }
}

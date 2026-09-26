<?php

namespace App\Filament\Coach\Resources\Events\Pages;

use App\Ai\Anthropic;
use App\Ai\Summarizer;
use App\Filament\Coach\Resources\Events\EventResource;
use App\Jobs\SummarizeEvent;
use App\Models\AiSummary;
use App\Models\Event;
use App\Recordings\Freigabe;
use App\Recordings\Vimeo;
use App\Recordings\Wache;
use App\Tenancy\CurrentTenant;
use App\Zoom\Anwesenheit;
use App\Zoom\Zoom;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditEvent extends EditRecord
{
    protected static string $resource = EventResource::class;

    protected function getHeaderActions(): array
    {
        $tenant = app(CurrentTenant::class)->get();

        return [
            Action::make('freigeben')->label('Aufzeichnung freigeben')->icon('heroicon-o-paper-airplane')->color('primary')
                ->visible(fn () => $this->record->hasRecording() && ! $this->record->recording_notified_at)
                ->modalHeading('Aufzeichnung freigeben?')
                ->modalDescription(fn () => ($this->record->user_id ? 'Die Person' : 'Alle im Programm').' bekommen Bescheid. Die Zusammenfassung steht in der Mail.')
                ->schema([
                    CheckboxList::make('wege')->label('Wie')->options(['mail' => 'Mail mit Zusammenfassung', 'push' => 'Push und Telegram'])->default(['mail', 'push'])->required(),
                ])
                ->action(function (array $data) {
                    $n = app(Freigabe::class)->freigeben($this->record, $data['wege']);
                    Notification::make()->title('Freigegeben, '.$n.' Person'.($n === 1 ? '' : 'en').' bekommen Bescheid')->success()->send();
                    $this->refreshFormData(['recording_status']);
                }),
            Action::make('vimeo')->label('Auf Vimeo suchen')->icon('heroicon-o-magnifying-glass')->color('gray')
                ->visible(fn () => app(Vimeo::class)->konfiguriert() && ! $this->record->recording_notified_at && $this->record->starts_at->isPast())
                ->action(function () {
                    $this->record->forceFill(['recording_status' => $this->record->recording_status === 'nicht_gefunden' || $this->record->recording_status === 'ohne_abschrift' ? null : $this->record->recording_status, 'recording_tries' => 0])->saveQuietly();
                    $b = app(Wache::class)->lauf();
                    $this->record->refresh();
                    $this->fillForm();
                    Notification::make()->title($this->record->hasRecording() ? 'Stand: '.(Event::RECORDING_STATUS[$this->record->recording_status] ?? 'Link da') : 'Noch nichts gefunden')
                        ->body($b['zugeordnet'].' zugeordnet, '.$b['abschriften'].' Abschriften, '.$b['zusammenfassungen'].' Zusammenfassungen')->send();
                }),
            Action::make('zoom')->label('Zoom-Anwesenheit')->icon('heroicon-o-user-group')->color('gray')
                ->visible(fn () => app(Zoom::class)->konfiguriert() && Zoom::nummer($this->record->zoom_url) && $this->record->starts_at->isPast())
                ->requiresConfirmation()->modalHeading('Teilnehmerliste aus Zoom holen?')
                ->modalDescription('Wer lange genug dabei war, wird als "live dabei" eingetragen. Was jemand selbst angegeben hat, bleibt.')
                ->action(function () {
                    $b = app(Anwesenheit::class)->abgleich($this->record);
                    $teile = array_filter([
                        $b['gesetzt'] ? 'Eingetragen: '.implode(', ', $b['gesetzt']) : null,
                        $b['unsicher'] ? 'Über den Namen: '.implode(', ', $b['unsicher']) : null,
                        $b['uebersprungen'] ? 'Übersprungen: '.implode(', ', $b['uebersprungen']) : null,
                        $b['fremd'] ? 'Nicht zugeordnet: '.implode(', ', $b['fremd']) : null,
                    ]);
                    Notification::make()->title($b['fehler'] ?? 'Anwesenheit übernommen')->body(implode("\n", $teile) ?: null)
                        ->{$b['fehler'] ? 'danger' : 'success'}()->persistent()->send();
                }),
            Action::make('zusammenfassen')->label('Zusammenfassen (KI)')->icon('heroicon-o-sparkles')
                ->visible(fn () => Anthropic::configured($tenant))
                ->disabled(fn () => blank($this->record->transcript))
                ->tooltip(fn () => blank($this->record->transcript) ? 'Erst die Abschrift eintragen.' : null)
                ->requiresConfirmation()->modalHeading('Zusammenfassung schreiben lassen?')
                ->modalDescription('Aus der Abschrift entstehen eine Zusammenfassung und Aufgabenvorschläge. Das dauert ein bis zwei Minuten, du bekommst Bescheid.')
                ->action(function () {
                    SummarizeEvent::dispatch(app(CurrentTenant::class)->id(), $this->record->id, auth()->id());
                    Notification::make()->title('Läuft. Du bekommst Bescheid, sobald es fertig ist.')->success()->send();
                }),

            Action::make('aufgaben')->label('Aufgaben aus Zusammenfassung')->icon('heroicon-o-clipboard-document-check')
                ->visible(fn () => $this->summary()?->isDone() && ! empty($this->summary()->tasks))
                ->schema(fn () => [
                    CheckboxList::make('gewaehlt')->label('Welche Vorschläge werden Aufgaben?')
                        ->options(collect($this->summary()->tasks)->mapWithKeys(fn ($t, $i) => [$i => $t['titel'].($t['fuer'] !== 'alle' ? ' (für '.$t['fuer'].')' : '').($t['text'] ? ' · '.$t['text'] : '')])->all())
                        ->default(array_keys($this->summary()->tasks))->required(),
                ])
                ->action(function (array $data) {
                    $n = app(Summarizer::class)->createTasks($this->summary(), array_map('intval', $data['gewaehlt']), auth()->user());
                    Notification::make()->title($n.' Aufgabe'.($n === 1 ? '' : 'n').' angelegt')->success()->send();
                }),

            DeleteAction::make(),
        ];
    }

    protected function summary(): ?AiSummary
    {
        return AiSummary::where('summarizable_type', 'event')->where('summarizable_id', $this->record->id)->where('kind', 'summary')->first();
    }
}

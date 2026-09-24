<?php

namespace App\Filament\Coach\Resources\Events\Pages;

use App\Ai\Anthropic;
use App\Ai\Summarizer;
use App\Filament\Coach\Resources\Events\EventResource;
use App\Jobs\SummarizeEvent;
use App\Models\AiSummary;
use App\Tenancy\CurrentTenant;
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

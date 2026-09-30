<?php

namespace App\Support\Protokoll;

use App\Models\Protokoll;
use App\Models\User;
use App\Tenancy\TenantScope;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;

/** Die Verlaufs-Tabelle, gleich im Coach-Bereich und in der Plattform (dort ueber alle Mandanten). */
class ProtokollTabelle
{
    public static function configure(Table $table, bool $plattform = false): Table
    {
        // subject_type speichert den Morph-Alias, nicht die Klasse
        $typen = collect(config('protokoll.typen'))->mapWithKeys(fn ($name, $klasse) => [Relation::getMorphAlias($klasse) => $name])->sort()->all();

        return $table
            ->modifyQueryUsing(fn (Builder $query) => ($plattform ? $query->withoutGlobalScope(TenantScope::class) : $query)->with(['causer', 'person']))
            ->columns(array_filter([
                TextColumn::make('created_at')->label('Wann')->dateTime('d.m.Y H:i')->sortable(),
                $plattform ? TextColumn::make('tenant.name')->label('Mandant')->placeholder('Plattform') : null,
                TextColumn::make('wer')->label('Wer')->state(fn (Protokoll $p) => $p->wer()),
                TextColumn::make('satz')->label('Was')->state(fn (Protokoll $p) => $p->satz())->wrap()
                    ->description(fn (Protokoll $p) => collect($p->aenderungen())->pluck('feld')->take(6)->join(', ')),
                TextColumn::make('person.name')->label('Person')->placeholder('')->toggleable(),
                TextColumn::make('event')->label('Ereignis')->badge()->formatStateUsing(fn (?string $state) => config('protokoll.ereignisse')[$state] ?? $state)
                    ->color(fn (?string $state) => match ($state) {
                        'created' => 'success', 'deleted' => 'danger', 'restored' => 'info', default => 'gray'
                    })->toggleable(isToggledHiddenByDefault: true),
            ]))
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('subject_type')->label('Was')->options($typen),
                SelectFilter::make('event')->label('Ereignis')->options(config('protokoll.ereignisse')),
                SelectFilter::make('person_id')->label('Person')->searchable()
                    ->options(fn () => User::whereIn('id', Protokoll::query()->when($plattform, fn ($q) => $q->withoutGlobalScope(TenantScope::class))->whereNotNull('person_id')->distinct()->pluck('person_id'))->orderBy('name')->pluck('name', 'id')->all()),
                SelectFilter::make('causer_id')->label('Wer')->searchable()
                    ->options(fn () => User::whereIn('id', Protokoll::query()->when($plattform, fn ($q) => $q->withoutGlobalScope(TenantScope::class))->whereNotNull('causer_id')->distinct()->pluck('causer_id'))->orderBy('name')->pluck('name', 'id')->all()),
                Filter::make('zeitraum')->schema([
                    DatePicker::make('von')->label('Von'),
                    DatePicker::make('bis')->label('Bis'),
                ])->query(fn (Builder $query, array $data) => $query
                    ->when($data['von'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
                    ->when($data['bis'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '<=', $d))),
            ])
            ->recordActions([
                Action::make('details')->label('Details')->icon('heroicon-o-eye')
                    ->modalHeading(fn (Protokoll $p) => $p->satz())
                    ->modalDescription(fn (Protokoll $p) => $p->wer().', '.$p->created_at->format('d.m.Y H:i'))
                    ->modalContent(fn (Protokoll $p) => view('filament.protokoll.details', ['eintrag' => $p]))
                    ->modalSubmitAction(false)->modalCancelActionLabel('Schliessen'),
            ])
            ->paginated([25, 50, 100])
            ->emptyStateHeading('Noch keine Einträge')
            ->emptyStateDescription('Hier steht künftig, wer wann was geändert hat.');
    }
}

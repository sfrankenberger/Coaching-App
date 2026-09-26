<?php

namespace App\Filament\Coach\Resources\Events\RelationManagers;

use App\Models\Membership;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** Wer abgesagt hat, live dabei war oder die Aufzeichnung gesehen hat. */
class AttendeesRelationManager extends RelationManager
{
    protected static string $relationship = 'attendees';

    protected static ?string $title = 'Teilnahme';

    protected static ?string $modelLabel = 'Eintrag';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('user_id')->label('Person')->required()->searchable()->native(false)
                ->options(fn () => Membership::query()->with('user')->get()->mapWithKeys(fn (Membership $m) => [$m->user_id => $m->user->name])->all()),
            Select::make('status')->label('Status')->options(['invited' => 'Eingeladen', 'declined' => 'Abgesagt', 'attended' => 'Live dabei', 'watched' => 'Aufzeichnung gesehen'])->default('invited')->native(false),
        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('user.name')
            ->columns([
                TextColumn::make('user.name')->label('Name'),
                TextColumn::make('status')->label('Status')->badge()->formatStateUsing(fn (string $state) => ['invited' => 'Eingeladen', 'declined' => 'Abgesagt', 'attended' => 'Live dabei', 'watched' => 'Gesehen'][$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        'declined' => 'danger', 'attended', 'watched' => 'success', default => 'gray'
                    }),
                TextColumn::make('attended_at')->label('Wann')->dateTime('d.m.Y H:i'),
            ])
            ->headerActions([CreateAction::make()->label('Eintrag')])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }
}

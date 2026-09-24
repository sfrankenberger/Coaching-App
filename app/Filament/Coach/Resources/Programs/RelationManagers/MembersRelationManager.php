<?php

namespace App\Filament\Coach\Resources\Programs\RelationManagers;

use App\Models\Membership;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Direkt eingetragene Personen eines Programms (unabhaengig von Angeboten).
 */
class MembersRelationManager extends RelationManager
{
    protected static string $relationship = 'members';

    protected static ?string $title = 'Teilnehmerinnen';

    protected static ?string $modelLabel = 'Teilnehmerin';

    protected static ?string $pluralModelLabel = 'Teilnehmerinnen';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('user_id')->label('Person')->required()->searchable()->native(false)
                ->options(fn () => Membership::query()->with('user')->get()->mapWithKeys(fn (Membership $m) => [$m->user_id => $m->user->name.' ('.$m->user->email.')'])->all()),
            Select::make('role_in_program')->label('Rolle')->options(['participant' => 'Teilnehmerin', 'coach' => 'Coach'])->default('participant')->native(false),
            TextInput::make('cohort')->label('Gruppe / Kohorte')->maxLength(60),
        ])->columns(3);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('user.name')
            ->columns([
                TextColumn::make('user.name')->label('Name')->searchable(),
                TextColumn::make('user.email')->label('E-Mail')->searchable(),
                TextColumn::make('role_in_program')->label('Rolle')->badge()->formatStateUsing(fn ($state) => $state === 'coach' ? 'Coach' : 'Teilnehmerin'),
                TextColumn::make('cohort')->label('Gruppe'),
                TextColumn::make('share_mode')->label('Freigabe')->formatStateUsing(fn ($state) => match ($state) {
                    'alles' => 'Alles geteilt', 'einzeln' => 'Einzeln', default => 'Noch offen'
                }),
                TextColumn::make('joined_at')->label('Dabei seit')->date('d.m.Y'),
                TextColumn::make('last_seen_at')->label('Zuletzt da')->since(),
            ])
            ->headerActions([
                CreateAction::make()->label('Person eintragen')->mutateDataUsing(fn (array $data) => $data + ['joined_at' => now()]),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()->label('Entfernen'),
            ]);
    }
}

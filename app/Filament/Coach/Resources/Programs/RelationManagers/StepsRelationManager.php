<?php

namespace App\Filament\Coach\Resources\Programs\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Schritte (Wochen oder Module) eines Programms.
 */
class StepsRelationManager extends RelationManager
{
    protected static string $relationship = 'steps';

    protected static ?string $title = 'Schritte';

    protected static ?string $modelLabel = 'Schritt';

    protected static ?string $pluralModelLabel = 'Schritte';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')->label('Titel')->required()->maxLength(160)->columnSpanFull(),
            TextInput::make('position')->label('Reihenfolge')->numeric()->default(fn () => ($this->getOwnerRecord()->steps()->max('position') ?? 0) + 1),
            TextInput::make('week_number')->label('Woche Nr.')->numeric(),
            DateTimePicker::make('unlocks_at')->label('Frei ab')->native(false)->displayFormat('d.m.Y H:i')->seconds(false)
                ->helperText('Nur bei wöchentlicher Taktung. Leer heisst sofort offen.'),
            RichEditor::make('summary')->label('Einleitung von dir')->columnSpanFull()
                ->toolbarButtons(['bold', 'italic', 'bulletList', 'link', 'undo', 'redo']),
        ])->columns(3);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->columns([
                TextColumn::make('position')->label('#')->sortable(),
                TextColumn::make('title')->label('Titel')->searchable(),
                TextColumn::make('week_number')->label('Woche'),
                TextColumn::make('unlocks_at')->label('Frei ab')->dateTime('d.m.Y H:i'),
                TextColumn::make('units_count')->label('Einheiten')->counts('units'),
            ])
            ->defaultSort('position')
            ->reorderable('position')
            ->headerActions([
                CreateAction::make()->label('Schritt anlegen'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}

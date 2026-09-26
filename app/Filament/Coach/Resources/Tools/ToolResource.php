<?php

namespace App\Filament\Coach\Resources\Tools;

use App\Filament\Coach\Resources\Tools\Pages\CreateTool;
use App\Filament\Coach\Resources\Tools\Pages\EditTool;
use App\Filament\Coach\Resources\Tools\Pages\ListTools;
use App\Models\Tool;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** Werkzeuge fuer die Coach-Ausbildung: Methoden mit Zweck, Einsatz, Ablauf, Beispiel. */
class ToolResource extends Resource
{
    protected static ?string $model = Tool::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWrenchScrewdriver;

    protected static ?string $modelLabel = 'Werkzeug';

    protected static ?string $pluralModelLabel = 'Werkzeuge';

    protected static ?string $navigationLabel = 'Werkzeuge';

    protected static ?int $navigationSort = 53;

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        $felder = [];
        foreach (Tool::FELDER as $key => [$label, $hilfe]) {
            $felder[] = in_array($key, ['duration'], true)
                ? TextInput::make($key)->label($label)->helperText($hilfe)->maxLength(120)
                : Textarea::make($key)->label($label)->helperText($hilfe)->rows($key === 'steps' ? 8 : 3)->columnSpanFull();
        }

        return $schema->components([
            Section::make('Werkzeug')->schema([
                TextInput::make('title')->label('Name')->required()->maxLength(160),
                TextInput::make('position')->label('Reihenfolge')->numeric()->default(0),
                Toggle::make('is_published')->label('Sichtbar für die Ausbildung')->helperText('Sehen nur Personen mit dem Kennzeichen "Coach-Ausbildung" und dein Team.'),
                Select::make('topics')->label('Themen')->relationship('topics', 'name')->multiple()->preload()->searchable(),
            ])->columns(2),
            Section::make('So ist es beschrieben')->description('Schreib so, dass eine Coachin es nachmachen kann.')->schema($felder)->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->label('Werkzeug')->searchable()->sortable(),
                TextColumn::make('purpose')->label('Wofür')->limit(70)->wrap()->toggleable(),
                TextColumn::make('duration')->label('Dauer')->toggleable(),
                TextColumn::make('topics.name')->label('Themen')->badge()->toggleable(),
                IconColumn::make('is_published')->label('Sichtbar')->boolean(),
                TextColumn::make('position')->label('Reihenfolge')->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('position')
            ->recordActions([EditAction::make()])
            ->emptyStateHeading('Noch kein Werkzeug')
            ->emptyStateDescription('Leg das erste an, dann siehst du, wie es sich anfühlt.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTools::route('/'),
            'create' => CreateTool::route('/create'),
            'edit' => EditTool::route('/{record}/edit'),
        ];
    }
}

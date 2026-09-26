<?php

namespace App\Filament\Coach\Resources\Topics;

use App\Filament\Coach\Resources\Topics\Pages\CreateTopic;
use App\Filament\Coach\Resources\Topics\Pages\EditTopic;
use App\Filament\Coach\Resources\Topics\Pages\ListTopics;
use App\Models\Topic;
use BackedEnum;
use Filament\Actions\EditAction;
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

/** Themen fuer den Themenfinder. Die Zuordnung passiert an den Inhalten selbst. */
class TopicResource extends Resource
{
    protected static ?string $model = Topic::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static ?string $modelLabel = 'Thema';

    protected static ?string $pluralModelLabel = 'Themen';

    protected static ?string $navigationLabel = 'Themen';

    protected static ?int $navigationSort = 52;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Thema')->schema([
                TextInput::make('name')->label('Name')->required()->maxLength(120),
                TextInput::make('position')->label('Reihenfolge')->numeric()->default(0),
                TextInput::make('group')->label('Gruppe')->maxLength(80)->helperText('Für die Auswahlliste im Nachschlagen, zum Beispiel "Beziehung" oder "Arbeit".')->datalist(fn () => Topic::whereNotNull('group')->distinct()->pluck('group')->all()),
                Textarea::make('description')->label('Kurz beschrieben')->rows(2)->columnSpanFull(),
                Toggle::make('is_visible')->label('Im Themenfinder sichtbar')->default(true),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Thema')->searchable()->sortable(),
                TextColumn::make('taggables_count')->label('Inhalte')->counts('taggables')->sortable(),
                TextColumn::make('position')->label('Reihenfolge')->sortable()->toggleable(),
                IconColumn::make('is_visible')->label('Sichtbar')->boolean(),
            ])
            ->defaultSort('name')
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTopics::route('/'),
            'create' => CreateTopic::route('/neu'),
            'edit' => EditTopic::route('/{record}/bearbeiten'),
        ];
    }
}

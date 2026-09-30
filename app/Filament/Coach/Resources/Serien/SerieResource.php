<?php

namespace App\Filament\Coach\Resources\Serien;

use App\Filament\Coach\Resources\Serien\Pages\CreateSerie;
use App\Filament\Coach\Resources\Serien\Pages\EditSerie;
use App\Filament\Coach\Resources\Serien\Pages\ListSerien;
use App\Models\Kontakt;
use App\Models\Serie;
use App\Newsletter\Kontakte;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
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

/** Serien (Autoresponder): ein Tag loest sie aus, die Mails gehen mit Abstand in Tagen raus. Freebie, Willkommensserie, Live-Abend. */
class SerieResource extends Resource
{
    protected static ?string $model = Serie::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQueueList;

    protected static ?string $modelLabel = 'Serie';

    protected static ?string $pluralModelLabel = 'Serien';

    protected static ?string $navigationLabel = 'Serien';

    protected static ?int $navigationSort = 65;

    protected static ?string $slug = 'serien';

    protected static ?string $recordTitleAttribute = 'titel';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Serie')->description('Sobald ein bestätigter Kontakt den Tag bekommt (Formular, Kauf, von Hand), startet die Serie einmal für ihn. Schritt mit 0 Tagen geht sofort raus, z. B. das Freebie.')->schema([
                TextInput::make('titel')->label('Name')->required()->maxLength(120),
                TextInput::make('tag')->label('Auslösender Tag')->required()->maxLength(80)->datalist(fn () => array_keys(app(Kontakte::class)->alleTags()))->dehydrateStateUsing(fn ($state) => Kontakt::tagSauber((string) $state)),
                Toggle::make('aktiv')->label('Aktiv')->default(true),
            ])->columns(3),
            Section::make('Mails')->schema([
                Repeater::make('schritte')->label('')->schema([
                    TextInput::make('tage')->label('Tage nach dem vorigen Schritt')->numeric()->minValue(0)->maxValue(365)->default(0)->required(),
                    TextInput::make('betreff')->label('Betreff')->required()->maxLength(150),
                    TextInput::make('titel')->label('Headline')->maxLength(150),
                    TextInput::make('bild_url')->label('Bild (URL)')->url()->maxLength(500),
                    Textarea::make('text')->label('Text')->rows(8)->required()->columnSpanFull()->helperText('{vorname} wird ersetzt. [Text](https://...) für Links.'),
                    TextInput::make('knopf_text')->label('Knopf')->maxLength(80),
                    TextInput::make('knopf_url')->label('Knopf führt zu')->url()->maxLength(500),
                ])->columns(2)->addActionLabel('Mail hinzufügen')->reorderable()->collapsible()->itemLabel(fn (array $state) => ($state['betreff'] ?? 'Mail').' · nach '.($state['tage'] ?? 0).' Tagen'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('titel')->label('Serie')->searchable()->description(fn (Serie $r) => 'Tag: '.$r->tag.' · '.count($r->schritte ?? []).' Mails'),
                IconColumn::make('aktiv')->label('Aktiv')->boolean(),
                TextColumn::make('laeufe_count')->label('Läuft für')->counts('laeufe')->suffix(' Kontakte'),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSerien::route('/'),
            'create' => CreateSerie::route('/neu'),
            'edit' => EditSerie::route('/{record}/bearbeiten'),
        ];
    }
}

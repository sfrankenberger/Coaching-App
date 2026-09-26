<?php

namespace App\Filament\Coach\Resources\BookingTypes;

use App\Filament\Coach\Resources\BookingTypes\Pages\ManageBookingTypes;
use App\Models\BookingType;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;

/** Buchbare Arten (Erstgespraech, 1:1 ...): Titel, Dauer, Puffer, Vorbereitungsfragen. */
class BookingTypeResource extends Resource
{
    protected static ?string $model = BookingType::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?string $modelLabel = 'Buchungsart';

    protected static ?string $pluralModelLabel = 'Buchungsarten';

    protected static ?string $navigationLabel = 'Buchung';

    protected static ?int $navigationSort = 45;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')->label('Titel')->required()->maxLength(160)->live(onBlur: true)
                ->afterStateUpdated(fn ($state, $set, $get) => blank($get('key')) ? $set('key', Str::slug((string) $state)) : null),
            TextInput::make('key')->label('Kürzel (für den Link)')->required()->maxLength(40)->alphaDash(),
            TextInput::make('event_title')->label('Titel im Termin')->maxLength(160)->placeholder('z. B. 1:1 Sitzung'),
            TextInput::make('duration')->label('Dauer (Minuten)')->numeric()->required()->default(60)->minValue(10)->maxValue(240),
            TextInput::make('block_minutes')->label('Samt Puffer (Minuten)')->numeric()->minValue(10)->maxValue(300)
                ->helperText('So viel Zeit muss im Kalenderblock frei sein, z. B. 75 bei 60 Minuten.'),
            TextInput::make('block_tag')->label('Zusatz im Kalenderblock')->maxLength(40)
                ->helperText('Steht dieses Wort zusätzlich im Titel eines Blocks, gilt der Block nur für diese Art.'),
            Toggle::make('is_open')->label('Ohne Kontingent buchbar')->helperText('Für Erstgespräche. Sonst braucht es offene Sitzungen aus einer Begleitung.'),
            Toggle::make('is_active')->label('Buchbar')->default(true),
            Textarea::make('text')->label('Kurzer Text')->rows(2)->columnSpanFull(),
            Repeater::make('questions')->label('Vorbereitungsfragen')->simple(TextInput::make('frage')->required()->maxLength(300))
                ->addActionLabel('Frage hinzufügen')->reorderable()->columnSpanFull(),
            TextInput::make('position')->label('Reihenfolge')->numeric()->default(0),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->label('Titel')->description(fn (BookingType $t) => $t->text ? Str::limit($t->text, 80) : null),
                TextColumn::make('duration')->label('Dauer')->suffix(' Min'),
                IconColumn::make('is_open')->label('Offen')->boolean(),
                IconColumn::make('is_active')->label('Buchbar')->boolean(),
            ])
            ->defaultSort('position')
            ->reorderable('position')
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageBookingTypes::route('/')];
    }
}

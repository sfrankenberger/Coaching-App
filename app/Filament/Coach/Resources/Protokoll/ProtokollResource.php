<?php

namespace App\Filament\Coach\Resources\Protokoll;

use App\Filament\Coach\Resources\Protokoll\Pages\ListProtokoll;
use App\Models\Protokoll;
use App\Support\Protokoll\ProtokollTabelle;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/** Aenderungsprotokoll: wer hat wann was geaendert. Nur lesen. */
class ProtokollResource extends Resource
{
    protected static ?string $model = Protokoll::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static ?string $modelLabel = 'Eintrag';

    protected static ?string $pluralModelLabel = 'Verlauf';

    protected static ?string $navigationLabel = 'Verlauf';

    protected static ?string $slug = 'verlauf';

    protected static ?int $navigationSort = 95;

    public static function table(Table $table): Table
    {
        return ProtokollTabelle::configure($table, plattform: false);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProtokoll::route('/'),
        ];
    }
}

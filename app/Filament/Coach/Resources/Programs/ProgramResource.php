<?php

namespace App\Filament\Coach\Resources\Programs;

use App\Filament\Coach\Resources\Programs\Pages\CreateProgram;
use App\Filament\Coach\Resources\Programs\Pages\EditProgram;
use App\Filament\Coach\Resources\Programs\Pages\ListPrograms;
use App\Filament\Coach\Resources\Programs\RelationManagers\MembersRelationManager;
use App\Filament\Coach\Resources\Programs\RelationManagers\StepsRelationManager;
use App\Filament\Coach\Resources\Programs\RelationManagers\UnitsRelationManager;
use App\Filament\Coach\Resources\Programs\Schemas\ProgramForm;
use App\Filament\Coach\Resources\Programs\Tables\ProgramsTable;
use App\Models\Program;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ProgramResource extends Resource
{
    protected static ?string $model = Program::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAcademicCap;

    protected static ?string $modelLabel = 'Programm';

    protected static ?string $pluralModelLabel = 'Programme';

    protected static ?string $navigationLabel = 'Programme';

    protected static ?int $navigationSort = 10;

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return ProgramForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProgramsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            StepsRelationManager::class,
            UnitsRelationManager::class,
            MembersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPrograms::route('/'),
            'create' => CreateProgram::route('/neu'),
            'edit' => EditProgram::route('/{record}/bearbeiten'),
        ];
    }
}

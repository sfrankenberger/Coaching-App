<?php

namespace App\Filament\Coach\Resources\Memberships;

use App\Filament\Coach\Resources\Memberships\Pages\CreateMembership;
use App\Filament\Coach\Resources\Memberships\Pages\Dossier;
use App\Filament\Coach\Resources\Memberships\Pages\EditMembership;
use App\Filament\Coach\Resources\Memberships\Pages\ListMemberships;
use App\Filament\Coach\Resources\Memberships\Schemas\MembershipForm;
use App\Filament\Coach\Resources\Memberships\Tables\MembershipsTable;
use App\Models\Membership;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Personen des Mandanten: eine Zeile je Mitgliedschaft (Person + Rolle + Status).
 * Die Person selbst (users) ist plattformweit, hier wird sie ueber die Mitgliedschaft bearbeitet.
 */
class MembershipResource extends Resource
{
    protected static ?string $model = Membership::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?string $modelLabel = 'Person';

    protected static ?string $pluralModelLabel = 'Personen';

    protected static ?string $navigationLabel = 'Personen';

    protected static ?string $recordTitleAttribute = 'user.name';

    public static function form(Schema $schema): Schema
    {
        return MembershipForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MembershipsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('user');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMemberships::route('/'),
            'create' => CreateMembership::route('/neu'),
            'edit' => EditMembership::route('/{record}/bearbeiten'),
            'dossier' => Dossier::route('/{record}/dossier'),
        ];
    }
}

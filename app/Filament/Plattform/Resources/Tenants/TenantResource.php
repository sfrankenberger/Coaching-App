<?php

namespace App\Filament\Plattform\Resources\Tenants;

use App\Filament\Plattform\Resources\Tenants\Pages\CreateTenant;
use App\Filament\Plattform\Resources\Tenants\Pages\EditTenant;
use App\Filament\Plattform\Resources\Tenants\Pages\ListTenants;
use App\Models\Tenant;
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

class TenantResource extends Resource
{
    protected static ?string $model = Tenant::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static ?string $modelLabel = 'Mandant';

    protected static ?string $pluralModelLabel = 'Mandanten';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Mandant')->schema([
                TextInput::make('name')->label('Name')->required()->maxLength(120),
                TextInput::make('slug')->label('Kürzel')->required()->alphaDash()->unique(ignoreRecord: true),
                TextInput::make('locale')->label('Sprache')->default('de_CH')->maxLength(10),
                TextInput::make('timezone')->label('Zeitzone')->default('Europe/Zurich'),
                TextInput::make('currency')->label('Währung')->default('CHF')->maxLength(3),
                Toggle::make('is_active')->label('Aktiv')->default(true),
            ])->columns(2),

            Section::make('Domains')->schema([
                Repeater::make('domains')->relationship()->label('')
                    ->schema([
                        TextInput::make('domain')->label('Domain')->required()->maxLength(190),
                        Toggle::make('is_primary')->label('Hauptdomain'),
                    ])->columns(2)->defaultItems(1)->addActionLabel('Domain hinzufügen'),
            ]),

            Section::make('Branding und Einstellungen')
                ->description('Schlüssel wie in App\Tenancy\Branding::DEFAULTS: app_name, primary, card_bg, font_heading, logo_url, icon_url ...')
                ->schema([
                    Textarea::make('branding')->label('JSON')->rows(14)
                        ->formatStateUsing(fn ($state) => json_encode($state ?: new \stdClass, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE))
                        ->dehydrateStateUsing(fn ($state) => json_decode((string) $state, true) ?? [])
                        ->rule('json'),
                    Textarea::make('settings')->label('Einstellungen (JSON)')->rows(14)
                        ->formatStateUsing(fn ($state) => json_encode($state ?: new \stdClass, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE))
                        ->dehydrateStateUsing(fn ($state) => json_decode((string) $state, true) ?? [])
                        ->rule('json'),
                ])->collapsed(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Name')->searchable()->sortable(),
                TextColumn::make('slug')->label('Kürzel'),
                TextColumn::make('domains.domain')->label('Domains')->listWithLineBreaks(),
                IconColumn::make('is_active')->label('Aktiv')->boolean(),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTenants::route('/'),
            'create' => CreateTenant::route('/neu'),
            'edit' => EditTenant::route('/{record}/bearbeiten'),
        ];
    }
}

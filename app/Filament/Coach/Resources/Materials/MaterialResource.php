<?php

namespace App\Filament\Coach\Resources\Materials;

use App\Filament\Coach\Resources\Materials\Pages\CreateMaterial;
use App\Filament\Coach\Resources\Materials\Pages\EditMaterial;
use App\Filament\Coach\Resources\Materials\Pages\ListMaterials;
use App\Models\Membership;
use App\Models\Resource as Material;
use App\Tenancy\CurrentTenant;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Material: Datei oder Link, zugeordnet zu Programmen, Einheiten, Terminen oder Personen.
 */
class MaterialResource extends Resource
{
    protected static ?string $model = Material::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFolderOpen;

    protected static ?string $modelLabel = 'Material';

    protected static ?string $pluralModelLabel = 'Material';

    protected static ?string $navigationLabel = 'Material';

    protected static ?int $navigationSort = 40;

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        $pivot = fn () => ['tenant_id' => app(CurrentTenant::class)->id()];

        return $schema->components([
            Section::make('Material')->schema([
                TextInput::make('title')->label('Titel')->required()->maxLength(160)->columnSpanFull(),
                Select::make('type')->label('Art')->options(Material::TYPES)->default('pdf')->required()->native(false),
                TextInput::make('duration')->label('Dauer / Umfang')->placeholder('z. B. 12 Seiten, 23 Min.')->maxLength(60),
                FileUpload::make('file_path')->label('Datei')->disk('local')
                    ->directory(fn () => 'tenants/'.app(CurrentTenant::class)->id().'/material')
                    ->storeFileNamesIn('file_name')->maxSize(51200)->columnSpanFull()
                    ->helperText('Oder unten eine Adresse eintragen (Vimeo, YouTube, Podcast, Link).'),
                TextInput::make('url')->label('Adresse (Link)')->url()->maxLength(1000)->columnSpanFull(),
                TextInput::make('image_url')->label('Vorschaubild (URL)')->url()->maxLength(500)->columnSpanFull(),
                Textarea::make('description')->label('Beschreibung')->rows(3)->columnSpanFull(),
                Toggle::make('is_archived')->label('Archiviert (nicht mehr anzeigen)'),
                Select::make('topics')->label('Themen')->relationship('topics', 'name')->multiple()->preload()->searchable()->columnSpanFull()->createOptionForm([TextInput::make('name')->label('Thema')->required()->maxLength(120)]),
            ])->columns(2),

            Section::make('Wo es erscheint')->schema([
                Select::make('programs')->label('Programme')->relationship('programs', 'title')->multiple()->preload()->pivotData($pivot),
                Select::make('units')->label('Einheiten')->relationship('units', 'title')->multiple()->searchable()->preload()->pivotData($pivot),
                Select::make('events')->label('Termine')->relationship('events', 'title')->multiple()->searchable()->preload()->pivotData($pivot),
                Select::make('users')->label('Direkt für Personen')->multiple()->searchable()->preload()
                    ->relationship('users', 'name', fn ($query) => $query->whereIn('users.id', Membership::query()->select('user_id')))
                    ->pivotData(fn () => $pivot() + ['shared_by' => auth()->id()]),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->label('Titel')->searchable()->sortable(),
                TextColumn::make('type')->label('Art')->badge()->formatStateUsing(fn (string $state) => Material::TYPES[$state] ?? $state),
                TextColumn::make('programs.title')->label('Programme')->listWithLineBreaks()->limitList(2)->toggleable(),
                TextColumn::make('users.name')->label('Für Personen')->listWithLineBreaks()->limitList(2)->toggleable(),
                TextColumn::make('created_at')->label('Angelegt')->date('d.m.Y')->sortable()->toggleable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([SelectFilter::make('type')->label('Art')->options(Material::TYPES)])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMaterials::route('/'),
            'create' => CreateMaterial::route('/neu'),
            'edit' => EditMaterial::route('/{record}/bearbeiten'),
        ];
    }
}

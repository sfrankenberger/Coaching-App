<?php

namespace App\Filament\Coach\Resources\Offers;

use App\Filament\Coach\Resources\Offers\Pages\CreateOffer;
use App\Filament\Coach\Resources\Offers\Pages\EditOffer;
use App\Filament\Coach\Resources\Offers\Pages\ListOffers;
use App\Filament\Coach\Resources\Offers\RelationManagers\EntitlementsRelationManager;
use App\Models\Offer;
use App\Tenancy\CurrentTenant;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
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
use Illuminate\Support\HtmlString;

/**
 * Angebote: was Zugang gibt. Produktzuordnung (WooCommerce, Stripe) und Programme.
 */
class OfferResource extends Resource
{
    protected static ?string $model = Offer::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static ?string $modelLabel = 'Angebot';

    protected static ?string $pluralModelLabel = 'Angebote';

    protected static ?string $navigationLabel = 'Angebote';

    protected static ?int $navigationSort = 20;

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Angebot')->schema([
                TextInput::make('title')->label('Titel')->required()->maxLength(160),
                Select::make('type')->label('Art')->options(Offer::TYPES)->required()->native(false),
                TextInput::make('access_days')->label('Zugang in Tagen')->numeric()->helperText('Leer heisst unbegrenzt. Einzelkauf bisher 365.'),
                Toggle::make('is_free')->label('Gratis'),
                Toggle::make('is_active')->label('Aktiv')->default(true),
                Select::make('programs')->label('Schaltet frei')->relationship('programs', 'title')->multiple()->preload()->columnSpanFull()
                    ->pivotData(fn () => ['tenant_id' => app(CurrentTenant::class)->id()]),
            ])->columns(3),

            Section::make('Verkauf')->description('Preis und Auftritt. Der Kauflink führt in die App, die Website zeigt das Angebot über den Shortcode. Ohne Preis ist das Angebot nur von Hand vergebbar.')->schema([
                TextInput::make('slug')->label('Kurzname im Link')->maxLength(120)->placeholder('entsteht aus dem Titel')->helperText('Nur Kleinbuchstaben, Ziffern und Bindestrich.')->rules(['nullable', 'regex:/^[a-z0-9-]+$/']),
                Toggle::make('settings.sichtbar')->label('Öffentlich sichtbar (Liste auf Website und in der App)')->inline(false),
                TextInput::make('settings.preis_chf')->label('Preis CHF')->numeric()->step(0.05)->minValue(0),
                TextInput::make('settings.preis_eur')->label('Preis EUR')->numeric()->step(0.05)->minValue(0),
                TextInput::make('settings.aktion_preis_chf')->label('Aktionspreis CHF')->numeric()->step(0.05)->minValue(0),
                TextInput::make('settings.aktion_preis_eur')->label('Aktionspreis EUR')->numeric()->step(0.05)->minValue(0),
                DatePicker::make('settings.aktion_bis')->label('Aktion gilt bis')->native(false)->displayFormat('d.m.Y'),
                Toggle::make('settings.kauf_rechnung')->label('Kauf auf Rechnung erlauben')->default(true)->inline(false),
                Textarea::make('settings.teaser')->label('Kurztext (Karte auf der Website, in der App)')->rows(3)->maxLength(400)->columnSpanFull(),
                TextInput::make('settings.bild_url')->label('Bild (URL)')->url()->maxLength(500)->columnSpanFull(),
                Placeholder::make('kauflink')->label('Kauflink und Shortcodes')
                    ->content(fn (?Offer $record) => $record?->slug
                        ? new HtmlString('<code>'.e($record->kaufUrl()).'</code><br><code>[app_kaufen slug="'.e($record->slug).'" text="Jetzt dabei sein"]</code> Knopf<br><code>[app_angebot slug="'.e($record->slug).'"]</code> Karte mit Preis<br><code>[app_angebote]</code> alle sichtbaren Angebote<br>Mit <code>ref="herbst-webinar"</code> merkt sich die App die Herkunft.')
                        : 'Nach dem Speichern.')
                    ->columnSpanFull(),
            ])->columns(2),

            Section::make('Produkte')->description('Welche Produkte im Shop dieses Angebot auslösen.')->schema([
                Repeater::make('products')->label('')->relationship()->schema([
                    Select::make('source')->label('Quelle')->options(['woocommerce' => 'WooCommerce', 'stripe' => 'Stripe', 'manual' => 'Von Hand'])->default('woocommerce')->required()->native(false),
                    TextInput::make('external_id')->label('Produkt-ID')->required()->maxLength(120),
                ])->columns(2)->defaultItems(0)->addActionLabel('Produkt zuordnen'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->label('Titel')->searchable()->sortable(),
                TextColumn::make('type')->label('Art')->badge()->formatStateUsing(fn (string $state) => Offer::TYPES[$state] ?? $state),
                TextColumn::make('programs.title')->label('Programme')->listWithLineBreaks()->limitList(3),
                TextColumn::make('products.external_id')->label('Produkte')->badge(),
                TextColumn::make('entitlements_count')->label('Zugänge')->counts('entitlements'),
                IconColumn::make('is_active')->label('Aktiv')->boolean(),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getRelations(): array
    {
        return [EntitlementsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOffers::route('/'),
            'create' => CreateOffer::route('/neu'),
            'edit' => EditOffer::route('/{record}/bearbeiten'),
        ];
    }
}

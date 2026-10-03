<?php

namespace App\Filament\Coach\Resources\Serien;

use App\Filament\Coach\Resources\Serien\Pages\CreateSerie;
use App\Filament\Coach\Resources\Serien\Pages\EditSerie;
use App\Filament\Coach\Resources\Serien\Pages\ListSerien;
use App\Models\Kontakt;
use App\Models\Serie;
use App\Newsletter\Bausteine;
use App\Newsletter\Kontakte;
use App\Newsletter\Serien;
use App\Models\Program;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

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
            Section::make('Was löst die Serie aus')->description('Eine Serie ist ein Autoresponder: Sobald ein bestätigter Kontakt den Tag bekommt, bekommt er die Mails der Reihe nach, mit dem Abstand in Tagen. Jeder Kontakt durchläuft eine Serie nur einmal.')->schema([
                TextInput::make('titel')->label('Name der Serie')->required()->maxLength(120)->placeholder('z. B. Willkommen, Live-Abend, Warteliste'),
                TextInput::make('tag')->label('Auslösender Tag')->required()->maxLength(80)->datalist(fn () => array_keys(app(Kontakte::class)->alleTags()))->dehydrateStateUsing(fn ($state) => Kontakt::tagSauber((string) $state))
                    ->helperText('Bestehenden Tag wählen oder einen neuen schreiben (klein, ohne Leerzeichen).'),
                Toggle::make('aktiv')->label('Aktiv')->default(true)->inline(false),
                Select::make('settings.program_id')->label('Kurs der Serie (für Bedingungen)')->options(fn () => Program::orderBy('title')->pluck('title', 'id')->all())->native(false)->nullable()
                    ->helperText('Nur nötig, wenn ein Schritt vom Stand im Kurs abhängt.'),
                Placeholder::make('anleitung')->label('So bekommt jemand den Tag')->columnSpanFull()->content(fn (Get $get) => new HtmlString(self::anleitung(Kontakt::tagSauber((string) ($get('tag') ?: 'mein-tag'))))),
            ])->columns(3)->columnSpanFull(),
            Section::make('Die Mails')->description('Schritt mit 0 Tagen geht sofort raus. Jede Mail hat denselben Baukasten wie ein Newsletter. Zum Prüfen: speichern, dann "Vorschau" am Schritt.')->schema([
                Repeater::make('schritte')->label('')->schema([
                    Grid::make(4)->schema([
                        TextInput::make('tage')->label('Tage nach dem vorigen Schritt')->numeric()->minValue(0)->maxValue(365)->default(0)->required()->live(onBlur: true),
                        TextInput::make('betreff')->label('Betreff')->required()->maxLength(150)->live(onBlur: true)->columnSpan(2),
                        TextInput::make('vorschautext')->label('Vorschautext')->maxLength(150),
                        Select::make('bedingung')->label('Nur schicken, wenn')->options(Serien::BEDINGUNGEN)->default('')->native(false)->columnSpan(2)
                            ->helperText('Trifft es nicht zu, wird der Schritt übersprungen. Im Text steht {anmeldelink} für einen frischen Einstiegslink.'),
                    ]),
                    Bausteine::feld('bloecke'),
                ])->addActionLabel('Mail hinzufügen')->reorderableWithButtons()->collapsible()->cloneable()
                    ->itemLabel(fn (array $state) => ($state['betreff'] ?? 'Mail').' · '.(((int) ($state['tage'] ?? 0)) === 0 ? 'sofort' : 'nach '.$state['tage'].' Tagen'))
                    ->extraItemActions([
                        Action::make('vorschau')->label('Vorschau')->icon('heroicon-o-eye')
                            ->url(fn (array $arguments, Repeater $component, ?Serie $record) => $record ? route('newsletter.vorschau.serie', ['serie' => $record, 'schritt' => array_search($arguments['item'], array_keys($component->getState() ?? []), true)]) : null, shouldOpenInNewTab: true)
                            ->visible(fn (?Serie $record) => (bool) $record),
                    ]),
            ])->columnSpanFull(),
        ]);
    }

    /** Wie ein Kontakt zum Tag kommt: Formular auf der Website oder Landingpage, Anmeldeseite der App, Kauf, von Hand. */
    public static function anleitung(string $tag): string
    {
        $url = route('newsletter.anmelden', ['tag' => $tag]);
        $z = fn (string $t) => '<li style="margin:0 0 6px;">'.$t.'</li>';

        return '<ul style="margin:0;padding-left:18px;line-height:1.5;">'
            .$z('<strong>Landingpage auf der Website:</strong> Shortcode <code>[app_anmelden tag="'.e($tag).'"]</code> (Formular mit Bestätigungsmail) oder <code>[app_anmelden tag="'.e($tag).'" sofort="1"]</code> (ohne Bestätigung, für Veranstaltungen).')
            .$z('<strong>Elementor-Formular:</strong> den Formularnamen in der Formular-Brücke (<code>lea-app-anmeldung.php</code>) dem Tag zuordnen.')
            .$z('<strong>Link zum Teilen:</strong> <a href="'.e($url).'" target="_blank" style="text-decoration:underline;">'.e($url).'</a>')
            .$z('<strong>Von Hand:</strong> unter Kontakte den Tag setzen. <strong>Kauf:</strong> Kundinnen bekommen den Slug des Angebots als Tag.')
            .'</ul>';
    }

    /** Je Schritt Klartext und Headline aus den Bausteinen ableiten; alte Schritte ohne Bausteine bleiben, wie sie sind. */
    public static function ausBausteinen(array $data): array
    {
        $data['schritte'] = array_values(array_map(function (array $s) {
            $bloecke = array_values(array_filter((array) ($s['bloecke'] ?? []), fn ($b) => is_array($b) && ! empty($b['type'])));
            $s['bloecke'] = $bloecke ?: null;
            if ($bloecke) {
                $s['text'] = Bausteine::text($bloecke) ?: ' ';
                $s['titel'] = Bausteine::titel($bloecke);
                $s['bild_url'] = null;
                $s['knopf_text'] = null;
                $s['knopf_url'] = null;
            }

            return $s;
        }, (array) ($data['schritte'] ?? [])));

        return $data;
    }

    /** Alte Schritte (Headline, Text, Bild, Knopf) als Bausteine zeigen. */
    public static function altZuBausteinen(array $data): array
    {
        $data['schritte'] = array_values(array_map(function (array $s) {
            if (empty($s['bloecke'])) {
                $s['bloecke'] = Bausteine::ausAlt($s['bild_url'] ?? null, $s['titel'] ?? null, $s['text'] ?? null, $s['knopf_text'] ?? null, $s['knopf_url'] ?? null);
            }

            return $s;
        }, (array) ($data['schritte'] ?? [])));

        return $data;
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

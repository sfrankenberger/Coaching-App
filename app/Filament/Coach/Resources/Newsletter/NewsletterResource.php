<?php

namespace App\Filament\Coach\Resources\Newsletter;

use App\Filament\Coach\Resources\Newsletter\Pages\CreateNewsletter;
use App\Filament\Coach\Resources\Newsletter\Pages\EditNewsletter;
use App\Filament\Coach\Resources\Newsletter\Pages\ListNewsletter;
use App\Models\Kontakt;
use App\Models\Newsletter;
use App\Newsletter\Bausteine;
use App\Newsletter\Kontakte;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

/** Newsletter: Baukasten (Ueberschrift, Text, Bild, Knopf, Trenner, Zitat, Kasten, Angebot) mit Live-Vorschau, Empfaenger nach Tags, Test, Versand in Wellen, Zahlen. */
class NewsletterResource extends Resource
{
    protected static ?string $model = Newsletter::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedNewspaper;

    protected static ?string $modelLabel = 'Newsletter';

    protected static ?string $pluralModelLabel = 'Newsletter';

    protected static ?string $navigationLabel = 'Newsletter';

    protected static ?int $navigationSort = 64;

    protected static ?string $slug = 'newsletter';

    protected static ?string $recordTitleAttribute = 'betreff';

    public static function form(Schema $schema): Schema
    {
        $gesperrt = fn (?Newsletter $record) => $record && ! $record->istEntwurf();

        return $schema->components([
            Section::make('Betreff')->schema([
                TextInput::make('betreff')->label('Betreff')->required()->maxLength(150)->live(onBlur: true),
                TextInput::make('vorschautext')->label('Vorschautext (steht im Postfach unter dem Betreff)')->maxLength(150),
            ])->columns(2)->disabled($gesperrt),
            Grid::make(5)->schema([
                Section::make('Inhalt')->description('Bausteine hinzufügen, mit den Pfeilen verschieben, zuklappen zum Sortieren. Platzhalter {vorname} und {name}.')->schema([
                    Bausteine::feld('bloecke'),
                ])->columnSpan(3)->disabled($gesperrt),
                Section::make('Vorschau')->description('So kommt die Mail an. Logo, Farben und Fusszeile stellst du unter Einstellungen ein.')->schema([
                    Placeholder::make('vorschau')->label('')->content(fn (Get $get) => self::vorschau($get('bloecke'), $get('betreff'))),
                ])->columnSpan(2),
            ]),
            Section::make('An wen und wann')->schema([
                TagsInput::make('tags')->label('An Kontakte mit Tag')->suggestions(fn () => array_keys(app(Kontakte::class)->alleTags()))->helperText('Leer: alle bestätigten Kontakte. Mehrere Tags: wer mindestens einen hat.'),
                DateTimePicker::make('geplant_at')->label('Geplant für')->native(false)->displayFormat('d.m.Y H:i')->seconds(false)->helperText('Leer: nur von Hand senden. Mit Zeit: geht dann von selbst raus (Status "geplant").'),
                Placeholder::make('empfaenger_stand')->label('Empfängerinnen')->content(fn (?Newsletter $record) => $record ? $record->empfaengerQuery()->count().' bestätigte Kontakte passen gerade' : 'nach dem Speichern')->columnSpanFull(),
            ])->columns(2)->disabled($gesperrt),
        ]);
    }

    /** Live-Vorschau im Formular: die Bausteine als fertige Mail in einem Rahmen, wie bei einer Empfaengerin. */
    public static function vorschau(mixed $bloecke, ?string $betreff = null): HtmlString
    {
        $bloecke = array_values(array_filter((array) $bloecke, fn ($b) => is_array($b) && ! empty($b['type'])));
        $user = auth()->user();
        $k = new Kontakt(['email' => $user?->email ?? 'du@example.com', 'name' => $user?->name ?? 'Anna Muster', 'token' => str_repeat('x', 40)]);
        $n = new Newsletter(['betreff' => $betreff ?? '', 'bloecke' => $bloecke ?: [['type' => 'text', 'data' => ['html' => '<p><em>Noch keine Bausteine. Füge links den ersten hinzu.</em></p>']]]]);
        $html = view('newsletter.vorschau', ['n' => $n, 'k' => $k])->render();

        return new HtmlString('<div style="font-size:13px;color:#777;margin:0 0 6px;">Betreff: <strong style="color:#222;">'.e($betreff ?: '(ohne Betreff)').'</strong></div>'
            .'<iframe title="Vorschau" srcdoc="'.e($html).'" style="width:100%;height:760px;border:1px solid #e5e5e0;border-radius:12px;background:#fff;"></iframe>');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('betreff')->label('Betreff')->searchable()->limit(60)->description(fn (Newsletter $r) => ($r->tags ? 'Tags: '.implode(', ', $r->tags) : 'alle').($r->geplant_at && $r->status === 'geplant' ? ' · geplant '.$r->geplant_at->format('d.m.Y H:i') : '')),
                TextColumn::make('status')->label('Stand')->badge()->formatStateUsing(fn ($s) => Newsletter::STATUS[$s] ?? $s)->color(fn ($s) => match ($s) {
                    'gesendet' => 'success', 'laeuft' => 'warning', 'geplant' => 'info', default => 'gray'
                }),
                TextColumn::make('gesendet')->label('Gesendet')->formatStateUsing(fn ($s, Newsletter $r) => $r->empfaenger ? "$s / {$r->empfaenger}" : '')->toggleable(),
                TextColumn::make('geoeffnet')->label('Geöffnet')->formatStateUsing(fn ($s, Newsletter $r) => $r->gesendet ? $s.' ('.round($s / $r->gesendet * 100).' %)' : '')->toggleable(),
                TextColumn::make('geklickt')->label('Geklickt')->formatStateUsing(fn ($s, Newsletter $r) => $r->gesendet ? $s.' ('.round($s / $r->gesendet * 100).' %)' : '')->toggleable(),
                TextColumn::make('gesendet_at')->label('Datum')->dateTime('d.m.Y H:i')->sortable()->toggleable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([SelectFilter::make('status')->label('Stand')->options(Newsletter::STATUS)])
            ->recordActions([EditAction::make()]);
    }

    /** Aus den Bausteinen Klartext und Headline ableiten (Liste, Suche, Werkzeuge); ohne Bausteine bleiben die alten Felder. */
    public static function ausBausteinen(array $data): array
    {
        $bloecke = array_values(array_filter((array) ($data['bloecke'] ?? []), fn ($b) => is_array($b) && ! empty($b['type'])));
        $data['bloecke'] = $bloecke ?: null;
        if ($bloecke) {
            $data['text'] = Bausteine::text($bloecke) ?: ' ';
            $data['titel'] = Bausteine::titel($bloecke);
            $data['bild_url'] = null;
            $data['knopf_text'] = null;
            $data['knopf_url'] = null;
        }

        return $data;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListNewsletter::route('/'),
            'create' => CreateNewsletter::route('/neu'),
            'edit' => EditNewsletter::route('/{record}/bearbeiten'),
        ];
    }
}

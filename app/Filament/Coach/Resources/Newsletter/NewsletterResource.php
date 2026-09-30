<?php

namespace App\Filament\Coach\Resources\Newsletter;

use App\Filament\Coach\Resources\Newsletter\Pages\CreateNewsletter;
use App\Filament\Coach\Resources\Newsletter\Pages\EditNewsletter;
use App\Filament\Coach\Resources\Newsletter\Pages\ListNewsletter;
use App\Models\Newsletter;
use App\Newsletter\Kontakte;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/** Newsletter: Vorlage mit Bild, Headline, Text und Knopf, Empfaenger nach Tags, Test, Versand in Wellen, Zahlen. */
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
        return $schema->components([
            Section::make('Mail')->description('Platzhalter: {vorname}, {name}. Links einfach als Adresse oder [Text](https://...). Leerzeile = neuer Absatz.')->schema([
                TextInput::make('betreff')->label('Betreff')->required()->maxLength(150),
                TextInput::make('vorschautext')->label('Vorschautext (Postfach)')->maxLength(150),
                TextInput::make('bild_url')->label('Bild oben (URL)')->url()->maxLength(500),
                TextInput::make('titel')->label('Headline')->maxLength(150),
                Textarea::make('text')->label('Text')->rows(14)->required()->columnSpanFull(),
                TextInput::make('knopf_text')->label('Knopf')->maxLength(80)->placeholder('z. B. Jetzt anmelden'),
                TextInput::make('knopf_url')->label('Knopf führt zu')->url()->maxLength(500),
            ])->columns(2)->disabled(fn (?Newsletter $record) => $record && ! $record->istEntwurf()),
            Section::make('An wen und wann')->schema([
                TagsInput::make('tags')->label('An Kontakte mit Tag')->suggestions(fn () => array_keys(app(Kontakte::class)->alleTags()))->helperText('Leer: alle bestätigten Kontakte. Mehrere Tags: wer mindestens einen hat.'),
                DateTimePicker::make('geplant_at')->label('Geplant für')->native(false)->displayFormat('d.m.Y H:i')->seconds(false)->helperText('Leer: nur von Hand senden. Mit Zeit: geht dann von selbst raus (Status "geplant").'),
                Placeholder::make('empfaenger_stand')->label('Empfängerinnen')->content(fn (?Newsletter $record) => $record ? $record->empfaengerQuery()->count().' bestätigte Kontakte passen gerade' : 'nach dem Speichern')->columnSpanFull(),
            ])->columns(2)->disabled(fn (?Newsletter $record) => $record && ! $record->istEntwurf()),
        ]);
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

    public static function getPages(): array
    {
        return [
            'index' => ListNewsletter::route('/'),
            'create' => CreateNewsletter::route('/neu'),
            'edit' => EditNewsletter::route('/{record}/bearbeiten'),
        ];
    }
}

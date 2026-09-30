<?php

namespace App\Filament\Coach\Resources\Kontakte;

use App\Filament\Coach\Resources\Kontakte\Pages\CreateKontakt;
use App\Filament\Coach\Resources\Kontakte\Pages\EditKontakt;
use App\Filament\Coach\Resources\Kontakte\Pages\ListKontakte;
use App\Models\Kontakt;
use App\Newsletter\Kontakte;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

/** Kontakte: die Stufe unter Gast. Mailadresse, Name, Einwilligung mit Nachweis, Tags statt Listen. */
class KontaktResource extends Resource
{
    protected static ?string $model = Kontakt::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static ?string $modelLabel = 'Kontakt';

    protected static ?string $pluralModelLabel = 'Kontakte';

    protected static ?string $navigationLabel = 'Kontakte';

    protected static ?int $navigationSort = 63;

    protected static ?string $slug = 'kontakte';

    protected static ?string $recordTitleAttribute = 'email';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Kontakt')->schema([
                TextInput::make('email')->label('E-Mail')->email()->required()->maxLength(190),
                TextInput::make('name')->label('Name')->maxLength(120),
                Select::make('status')->label('Stand')->options(Kontakt::STATUS)->required()->native(false)->default('bestaetigt')
                    ->helperText('Von Hand angelegte Kontakte gelten als bestätigt (die Einwilligung liegt dir vor). Sonst "wartet auf Bestätigung", dann geht die Bestätigungsmail raus.'),
                TagsInput::make('tags')->label('Tags')->suggestions(fn () => array_keys(app(Kontakte::class)->alleTags()))->helperText('Kleinbuchstaben, Bindestrich. Ein Tag kann eine Serie auslösen.'),
                Placeholder::make('einwilligung_stand')->label('Einwilligung')->content(fn (?Kontakt $record) => $record?->einwilligung
                    ? collect($record->einwilligung)->map(fn ($v, $k) => $k.': '.(is_array($v) ? json_encode($v) : $v))->join(' · ')
                    : 'noch keine (wird beim Speichern mit Datum und "von Hand" vermerkt)')->columnSpanFull(),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('email')->label('E-Mail')->searchable()->sortable()->description(fn (Kontakt $r) => $r->name),
                TextColumn::make('status')->label('Stand')->badge()->formatStateUsing(fn ($state) => Kontakt::STATUS[$state] ?? $state)
                    ->color(fn ($state) => match ($state) {
                        'bestaetigt' => 'success', 'angemeldet' => 'warning', 'abgemeldet' => 'gray', default => 'danger'
                    }),
                TextColumn::make('tags')->label('Tags')->badge()->limitList(4),
                TextColumn::make('user_id')->label('Konto')->formatStateUsing(fn ($state) => $state ? 'Mitglied' : '')->toggleable(),
                TextColumn::make('bestaetigt_at')->label('Bestätigt')->dateTime('d.m.Y')->sortable()->toggleable(),
                TextColumn::make('created_at')->label('Seit')->dateTime('d.m.Y')->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')->label('Stand')->options(Kontakt::STATUS),
                Filter::make('tag')->label('Tag')->schema([Select::make('tag')->label('Tag')->options(fn () => collect(app(Kontakte::class)->alleTags())->mapWithKeys(fn ($n, $t) => [$t => "$t ($n)"])->all())->native(false)])
                    ->query(fn ($query, array $data) => filled($data['tag'] ?? null) ? $query->mitTags([$data['tag']]) : $query),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('bestaetigung')->label('Bestätigung schicken')->icon('heroicon-o-envelope')->visible(fn (Kontakt $r) => $r->status === 'angemeldet')
                    ->action(function (Kontakt $r) {
                        app(Kontakte::class)->bestaetigungSchicken($r);
                        Notification::make()->title('Bestätigungsmail an '.$r->email.' geschickt')->success()->send();
                    }),
            ])
            ->toolbarActions([
                BulkAction::make('taggen')->label('Tag vergeben')->icon('heroicon-o-tag')
                    ->schema([TextInput::make('tag')->label('Tag')->required()->maxLength(80)])
                    ->action(function (Collection $records, array $data) {
                        foreach ($records as $r) {
                            app(Kontakte::class)->taggen($r, [$data['tag']]);
                        }
                        Notification::make()->title('Tag '.Kontakt::tagSauber($data['tag']).' bei '.$records->count().' Kontakten gesetzt')->success()->send();
                    })->deselectRecordsAfterCompletion(),
                BulkAction::make('enttaggen')->label('Tag entfernen')->icon('heroicon-o-x-mark')
                    ->schema([TextInput::make('tag')->label('Tag')->required()->maxLength(80)])
                    ->action(function (Collection $records, array $data) {
                        foreach ($records as $r) {
                            app(Kontakte::class)->enttaggen($r, [$data['tag']]);
                        }
                        Notification::make()->title('Tag entfernt')->success()->send();
                    })->deselectRecordsAfterCompletion(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListKontakte::route('/'),
            'create' => CreateKontakt::route('/neu'),
            'edit' => EditKontakt::route('/{record}/bearbeiten'),
        ];
    }
}

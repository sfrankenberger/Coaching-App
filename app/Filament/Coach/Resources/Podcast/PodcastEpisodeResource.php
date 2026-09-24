<?php

namespace App\Filament\Coach\Resources\Podcast;

use App\Filament\Coach\Resources\Podcast\Pages\CreatePodcastEpisode;
use App\Filament\Coach\Resources\Podcast\Pages\EditPodcastEpisode;
use App\Filament\Coach\Resources\Podcast\Pages\ListPodcastEpisodes;
use App\Models\PodcastEpisode;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
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
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/** Podcastfolgen (per Feed geholt oder von Hand), mit Kapiteln, FAQ und Zusammenfassung. */
class PodcastEpisodeResource extends Resource
{
    protected static ?string $model = PodcastEpisode::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMicrophone;

    protected static ?string $modelLabel = 'Podcastfolge';

    protected static ?string $pluralModelLabel = 'Podcast';

    protected static ?string $navigationLabel = 'Podcast';

    protected static ?int $navigationSort = 51;

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?string $slug = 'podcast';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Folge')->schema([
                TextInput::make('title')->label('Titel')->required()->maxLength(200)->columnSpanFull(),
                TextInput::make('show')->label('Sendung')->required()->maxLength(120)->datalist(fn () => PodcastEpisode::query()->distinct()->pluck('show')->all()),
                TextInput::make('episode_number')->label('Folge Nr.')->numeric(),
                TextInput::make('audio_url')->label('Audio (URL)')->url()->required()->maxLength(1000)->columnSpanFull(),
                TextInput::make('image_url')->label('Bild (URL)')->url()->maxLength(500),
                TextInput::make('url')->label('Link ins Web')->url()->maxLength(1000),
                TextInput::make('duration_seconds')->label('Dauer (Sekunden)')->numeric(),
                DateTimePicker::make('published_at')->label('Veröffentlicht am')->native(false)->displayFormat('d.m.Y H:i')->seconds(false)->default(now()),
                Textarea::make('summary')->label('Zusammenfassung')->rows(3)->columnSpanFull(),
                RichEditor::make('body')->label('Shownotes')->columnSpanFull()->toolbarButtons(['bold', 'italic', 'bulletList', 'link', 'undo', 'redo']),
                Select::make('topics')->label('Themen')->relationship('topics', 'name')->multiple()->preload()->searchable()->columnSpanFull()
                    ->createOptionForm([TextInput::make('name')->label('Thema')->required()->maxLength(120)]),
                Toggle::make('is_published')->label('Veröffentlicht')->default(true),
            ])->columns(2),

            Section::make('Kapitel und Fragen')->schema([
                Repeater::make('chapters')->label('Kapitel')->schema([
                    TextInput::make('start')->label('Start (Sek.)')->numeric()->required(),
                    TextInput::make('titel')->label('Titel')->required()->maxLength(160),
                ])->columns(2)->defaultItems(0)->addActionLabel('Kapitel'),
                Repeater::make('faq')->label('Fragen dazu')->schema([
                    TextInput::make('frage')->label('Frage')->required()->maxLength(200),
                    Textarea::make('antwort')->label('Antwort')->rows(2)->required(),
                ])->defaultItems(0)->addActionLabel('Frage'),
                Textarea::make('transcript')->label('Abschrift')->rows(8)->columnSpanFull(),
            ])->collapsed(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('published_at')->label('Datum')->date('d.m.Y')->sortable(),
                TextColumn::make('title')->label('Titel')->searchable()->limit(60)->description(fn (PodcastEpisode $r) => $r->show.($r->episode_number ? ' · Folge '.$r->episode_number : '')),
                TextColumn::make('duration_seconds')->label('Dauer')->formatStateUsing(fn ($state, PodcastEpisode $record) => $record->durationLabel())->toggleable(),
                IconColumn::make('summary')->label('Zusammenfassung')->boolean()->toggleable(),
                IconColumn::make('is_published')->label('Veröffentlicht')->boolean(),
            ])
            ->defaultSort('published_at', 'desc')
            ->filters([
                SelectFilter::make('show')->label('Sendung')->options(fn () => PodcastEpisode::query()->distinct()->orderBy('show')->pluck('show', 'show')->all()),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPodcastEpisodes::route('/'),
            'create' => CreatePodcastEpisode::route('/neu'),
            'edit' => EditPodcastEpisode::route('/{record}/bearbeiten'),
        ];
    }
}

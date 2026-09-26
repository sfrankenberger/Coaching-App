<?php

namespace App\Filament\Coach\Resources\Posts;

use App\Filament\Coach\Resources\Posts\Pages\CreatePost;
use App\Filament\Coach\Resources\Posts\Pages\EditPost;
use App\Filament\Coach\Resources\Posts\Pages\ListPosts;
use App\Models\Post;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DateTimePicker;
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

/**
 * Impulse und Neuigkeiten: hier geschrieben oder per Feed bzw. Import aus WordPress.
 * Beim Veroeffentlichen mit gewaehlten Kanaelen bekommen die Mitglieder Bescheid.
 */
class PostResource extends Resource
{
    protected static ?string $model = Post::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static ?string $modelLabel = 'Impuls';

    protected static ?string $pluralModelLabel = 'Impulse';

    protected static ?string $navigationLabel = 'Impulse';

    protected static ?int $navigationSort = 50;

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Beitrag')->schema([
                TextInput::make('title')->label('Titel')->required()->maxLength(200)->columnSpanFull(),
                Select::make('type')->label('Art')->options(Post::TYPES)->default('impuls')->required()->native(false),
                DateTimePicker::make('published_at')->label('Veröffentlichen am')->native(false)->displayFormat('d.m.Y H:i')->seconds(false)->default(now())
                    ->helperText('Liegt die Zeit in der Zukunft, erscheint der Beitrag erst dann.'),
                Textarea::make('excerpt')->label('Kurz gesagt')->rows(2)->maxLength(400)->columnSpanFull()->helperText('Erscheint in der Liste und in der Benachrichtigung.'),
                RichEditor::make('body')->label('Text')->columnSpanFull()
                    ->toolbarButtons(['bold', 'italic', 'h2', 'h3', 'bulletList', 'orderedList', 'link', 'blockquote', 'undo', 'redo']),
                TextInput::make('image_url')->label('Bild (URL)')->url()->maxLength(500),
                TextInput::make('url')->label('Link ins Web')->url()->maxLength(1000),
                Select::make('topics')->label('Themen')->relationship('topics', 'name')->multiple()->preload()->searchable()->columnSpanFull()
                    ->createOptionForm([TextInput::make('name')->label('Thema')->required()->maxLength(120)]),
            ])->columns(2),

            Section::make('Wer es sieht und erfährt')->schema([
                Select::make('visibility')->label('Sichtbar für')->options(Post::VISIBILITIES)->default('members')->required()->native(false)->live(),
                Select::make('program_id')->label('Programm')->relationship('program', 'title')->native(false)->preload()
                    ->visible(fn ($get) => $get('visibility') === 'program')->required(fn ($get) => $get('visibility') === 'program'),
                CheckboxList::make('notify_channels')->label('Bescheid geben per')->options(['push' => 'Push und Telegram (wer es hat)', 'mail' => 'Mail (wer kein Push hat)'])
                    ->helperText('Geht einmal raus, sobald der Beitrag sichtbar ist. Leer lassen heisst: still veröffentlichen.')->columnSpanFull(),
                Toggle::make('is_published')->label('Veröffentlicht')->default(true),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('published_at')->label('Datum')->dateTime('d.m.Y H:i')->sortable(),
                TextColumn::make('title')->label('Titel')->searchable()->limit(60)->description(fn (Post $r) => $r->typeLabel().' · '.($r->source === 'app' ? 'hier geschrieben' : $r->source)),
                TextColumn::make('visibility')->label('Sichtbar')->formatStateUsing(fn (string $state) => Post::VISIBILITIES[$state] ?? $state)->toggleable(),
                TextColumn::make('topics.name')->label('Themen')->badge()->limitList(2)->toggleable(),
                IconColumn::make('notified_at')->label('Gemeldet')->boolean()->toggleable(),
                IconColumn::make('is_published')->label('Veröffentlicht')->boolean(),
            ])
            ->defaultSort('published_at', 'desc')
            ->filters([
                SelectFilter::make('type')->label('Art')->options(Post::TYPES),
                SelectFilter::make('source')->label('Quelle')->options(['app' => 'Hier geschrieben', 'wordpress' => 'WordPress', 'feed' => 'Feed']),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPosts::route('/'),
            'create' => CreatePost::route('/neu'),
            'edit' => EditPost::route('/{record}/bearbeiten'),
        ];
    }
}

<?php

namespace App\Filament\Coach\Resources\Events;

use App\Filament\Coach\Resources\Events\Pages\CreateEvent;
use App\Filament\Coach\Resources\Events\Pages\EditEvent;
use App\Filament\Coach\Resources\Events\Pages\ListEvents;
use App\Filament\Coach\Resources\Events\RelationManagers\AttendeesRelationManager;
use App\Models\Event;
use App\Models\Membership;
use App\Models\ProgramStep;
use BackedEnum;
use Filament\Actions\EditAction;
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
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class EventResource extends Resource
{
    protected static ?string $model = Event::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?string $modelLabel = 'Termin';

    protected static ?string $pluralModelLabel = 'Termine';

    protected static ?string $navigationLabel = 'Termine';

    protected static ?int $navigationSort = 30;

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Termin')->schema([
                TextInput::make('title')->label('Titel')->required()->maxLength(160)->columnSpanFull(),
                Select::make('type')->label('Art')->options(Event::TYPES)->required()->native(false)->live()
                    ->afterStateUpdated(fn ($state, callable $set) => $set('all_day', in_array($state, Event::ALL_DAY_TYPES, true))),
                Select::make('program_id')->label('Programm')->relationship('program', 'title')->searchable()->preload()->native(false)->live(),
                Select::make('step_id')->label('Schritt / Woche')->native(false)
                    ->options(fn ($get) => ProgramStep::where('program_id', $get('program_id') ?: 0)->orderBy('position')->pluck('title', 'id')->all()),
                Select::make('user_id')->label('Person (bei 1:1)')->searchable()->native(false)
                    ->options(fn () => Membership::query()->with('user')->get()->mapWithKeys(fn (Membership $m) => [$m->user_id => $m->user->name])->all())
                    ->visible(fn ($get) => $get('type') === 'one_on_one'),
                DateTimePicker::make('starts_at')->label('Beginn')->required()->native(false)->displayFormat('d.m.Y H:i')->seconds(false),
                DateTimePicker::make('ends_at')->label('Ende')->native(false)->displayFormat('d.m.Y H:i')->seconds(false),
                Toggle::make('all_day')->label('Ganzer Tag'),
                TextInput::make('location')->label('Ort')->placeholder('Online via Zoom')->maxLength(160),
                TextInput::make('zoom_url')->label('Zoom-Link')->url()->maxLength(500)->columnSpanFull(),
                RichEditor::make('description')->label('Beschreibung')->columnSpanFull()->toolbarButtons(['bold', 'italic', 'bulletList', 'link', 'undo', 'redo']),
                Toggle::make('is_published')->label('Sichtbar')->default(true),
                Select::make('topics')->label('Themen')->relationship('topics', 'name')->multiple()->preload()->searchable()->columnSpanFull()->createOptionForm([TextInput::make('name')->label('Thema')->required()->maxLength(120)]),
            ])->columns(2),

            Section::make('Aufzeichnung')->schema([
                TextInput::make('recording_url')->label('Aufzeichnung (Vimeo-Link)')->url()->maxLength(500)->columnSpanFull()
                    ->helperText('Sobald hier ein Link steht, bekommen die Teilnehmerinnen Bescheid.'),
                TextInput::make('recording_duration')->label('Dauer')->placeholder('z. B. 58 Min.')->maxLength(60),
                Textarea::make('summary')->label('Zusammenfassung')->rows(6)->columnSpanFull(),
                Textarea::make('transcript')->label('Abschrift')->rows(6)->columnSpanFull(),
            ])->columns(2)->collapsed(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('starts_at')->label('Wann')->dateTime('D, d.m.Y H:i')->sortable(),
                TextColumn::make('title')->label('Titel')->searchable()->description(fn (Event $r) => $r->typeLabel()),
                TextColumn::make('program.title')->label('Programm')->toggleable(),
                TextColumn::make('user.name')->label('Person')->toggleable(),
                IconColumn::make('recording_url')->label('Aufzeichnung')->boolean(),
                TextColumn::make('attendees_count')->label('Absagen')->counts(['attendees' => fn ($q) => $q->where('status', 'declined')]),
                IconColumn::make('is_published')->label('Sichtbar')->boolean()->toggleable(),
            ])
            ->defaultSort('starts_at', 'desc')
            ->filters([
                SelectFilter::make('program_id')->label('Programm')->relationship('program', 'title'),
                SelectFilter::make('type')->label('Art')->options(Event::TYPES),
                Filter::make('kommend')->label('Nur kommende')->query(fn (Builder $query) => $query->where('starts_at', '>=', now()->startOfDay()))->default(),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getRelations(): array
    {
        return [AttendeesRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEvents::route('/'),
            'create' => CreateEvent::route('/neu'),
            'edit' => EditEvent::route('/{record}/bearbeiten'),
        ];
    }
}

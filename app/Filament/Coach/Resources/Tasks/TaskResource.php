<?php

namespace App\Filament\Coach\Resources\Tasks;

use App\Filament\Coach\Resources\Tasks\Pages\CreateTask;
use App\Filament\Coach\Resources\Tasks\Pages\EditTask;
use App\Filament\Coach\Resources\Tasks\Pages\ListTasks;
use App\Models\Membership;
use App\Models\Note;
use App\Models\Task;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
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
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;

/**
 * Aufgaben, die die Coachin gibt: an eine Person oder an alle in einem Programm.
 */
class TaskResource extends Resource
{
    protected static ?string $model = Task::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static ?string $modelLabel = 'Aufgabe';

    protected static ?string $pluralModelLabel = 'Aufgaben';

    protected static ?string $navigationLabel = 'Aufgaben';

    protected static ?int $navigationSort = 50;

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Aufgabe')->schema([
                TextInput::make('title')->label('Titel')->required()->maxLength(160)->columnSpanFull(),
                Textarea::make('body')->label('Hinweis')->rows(3)->columnSpanFull(),
                Select::make('user_id')->label('Für wen')->searchable()->native(false)
                    ->options(fn () => Membership::query()->with('user')->get()->mapWithKeys(fn (Membership $m) => [$m->user_id => $m->user->name])->all())
                    ->required(fn ($get, $livewire) => ! ($livewire instanceof CreateTask) || ! $get('fuer_programm'))
                    ->hidden(fn ($get, $livewire) => $livewire instanceof CreateTask && $get('fuer_programm')),
                Select::make('program_id')->label('Programm')->relationship('program', 'title')->preload()->native(false)->live(),
                Toggle::make('fuer_programm')->label('An alle im Programm')->dehydrated(false)->live()
                    ->visible(fn ($livewire) => $livewire instanceof CreateTask)
                    ->helperText('Legt für jede Teilnehmerin des Programms eine eigene Aufgabe an.'),
                DatePicker::make('due_at')->label('Bis')->native(false)->displayFormat('d.m.Y'),
                TextInput::make('due_time')->label('Uhrzeit')->placeholder('19:00')->maxLength(5),
                Toggle::make('is_daily')->label('Jeden Tag'),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['user', 'program']))
            ->columns([
                TextColumn::make('title')->label('Titel')->searchable()->description(fn (Task $r) => $r->body ? Str::limit($r->body, 60) : null),
                TextColumn::make('user.name')->label('Person')->sortable()->searchable(),
                TextColumn::make('program.title')->label('Programm')->toggleable(),
                TextColumn::make('source')->label('Quelle')->badge()->formatStateUsing(fn (string $state) => Task::SOURCES[$state] ?? $state)->toggleable(),
                TextColumn::make('due_at')->label('Bis')->date('d.m.Y')->sortable(),
                IconColumn::make('done_at')->label('Erledigt')->boolean()->sortable(),
                TextColumn::make('visibility')->label('Sichtbar')->formatStateUsing(fn (string $state) => Note::VISIBILITIES[$state] ?? $state)->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('user_id')->label('Person')->options(fn () => Membership::query()->with('user')->get()->mapWithKeys(fn (Membership $m) => [$m->user_id => $m->user->name])->all()),
                SelectFilter::make('program_id')->label('Programm')->relationship('program', 'title'),
                TernaryFilter::make('done_at')->label('Erledigt')->nullable()->trueLabel('Erledigt')->falseLabel('Offen')->queries(true: fn ($query) => $query->whereNotNull('done_at'), false: fn ($query) => $query->whereNull('done_at')),
                SelectFilter::make('source')->label('Quelle')->options(Task::SOURCES),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTasks::route('/'),
            'create' => CreateTask::route('/neu'),
            'edit' => EditTask::route('/{record}/bearbeiten'),
        ];
    }
}

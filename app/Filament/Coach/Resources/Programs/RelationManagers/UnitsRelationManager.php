<?php

namespace App\Filament\Coach\Resources\Programs\RelationManagers;

use App\Models\Exercise;
use App\Models\Unit;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;

/**
 * Einheiten eines Programms: Lektionen (Video, Text), Uebungen (Fragen) und Texte.
 */
class UnitsRelationManager extends RelationManager
{
    protected static string $relationship = 'units';

    protected static ?string $title = 'Einheiten';

    protected static ?string $modelLabel = 'Einheit';

    protected static ?string $pluralModelLabel = 'Einheiten';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Tabs::make()->tabs([
                Tab::make('Inhalt')->schema([
                    TextInput::make('title')->label('Titel')->required()->maxLength(160)->columnSpanFull(),
                    Select::make('step_id')->label('Schritt')->native(false)
                        ->options(fn () => $this->getOwnerRecord()->steps()->orderBy('position')->pluck('title', 'id')->all()),
                    Select::make('type')->label('Art')->options(Unit::TYPES)->default('lesson')->required()->native(false)->live(),
                    TextInput::make('position')->label('Reihenfolge')->numeric()->default(fn () => ($this->getOwnerRecord()->units()->max('position') ?? 0) + 1),
                    TextInput::make('duration_minutes')->label('Dauer (Min.)')->numeric(),
                    Textarea::make('intro')->label('Einleitung')->rows(3)->columnSpanFull(),
                    RichEditor::make('body')->label('Text')->columnSpanFull()
                        ->toolbarButtons(['bold', 'italic', 'h2', 'h3', 'bulletList', 'orderedList', 'link', 'blockquote', 'undo', 'redo']),
                    Toggle::make('is_published')->label('Veröffentlicht')->default(true),
                    Toggle::make('is_core')->label('Kernübung')->visible(fn ($get) => $get('type') === 'exercise_set'),
                    Select::make('topics')->label('Themen')->relationship('topics', 'name')->multiple()->preload()->searchable()->columnSpanFull()->createOptionForm([TextInput::make('name')->label('Thema')->required()->maxLength(120)]),
                ])->columns(2),

                Tab::make('Videos und Links')->schema([
                    Repeater::make('videos')->label('Videos')->schema([
                        TextInput::make('url')->label('Adresse (Vimeo, YouTube, mp4)')->url()->required(),
                        TextInput::make('title')->label('Titel'),
                    ])->columns(2)->defaultItems(0)->addActionLabel('Video hinzufügen')->reorderableWithButtons(),
                    Repeater::make('links')->label('Links')->schema([
                        TextInput::make('url')->label('Adresse')->url()->required(),
                        TextInput::make('title')->label('Titel'),
                    ])->columns(2)->defaultItems(0)->addActionLabel('Link hinzufügen'),
                ]),

                Tab::make('Übungsteile')->schema([
                    Repeater::make('exercises')->label('')->relationship()->orderColumn('position')
                        ->schema([
                            Select::make('type')->label('Art')->options(Exercise::TYPES)->default('text')->required()->native(false)->live(),
                            TextInput::make('title')->label('Titel (kurz)')->maxLength(160),
                            Textarea::make('prompt')->label('Frage oder Text')->rows(2)->columnSpanFull(),
                            TagsInput::make('options.values')->label('Werte, eine je Eintrag')
                                ->visible(fn ($get) => in_array($get('type'), ['values', 'choice'], true))->columnSpanFull(),
                            TextInput::make('options.platzhalter')->label('Platzhalter im Feld')->maxLength(160)
                                ->visible(fn ($get) => in_array($get('type'), ['list', 'letter'], true)),
                            TextInput::make('options.mehr')->label('Knopf für eine neue Zeile')->placeholder('Noch eine')->maxLength(60)
                                ->visible(fn ($get) => in_array($get('type'), ['list', 'pairs'], true)),
                            TextInput::make('options.links')->label('Überschrift links')->placeholder('Der Gedanke')->maxLength(60)
                                ->visible(fn ($get) => $get('type') === 'pairs'),
                            TextInput::make('options.rechts')->label('Überschrift rechts')->placeholder('Umgedreht')->maxLength(60)
                                ->visible(fn ($get) => $get('type') === 'pairs'),
                            TextInput::make('options.zeilen')->label('Höhe in Zeilen')->numeric()->minValue(4)->maxValue(40)->placeholder('16')
                                ->visible(fn ($get) => $get('type') === 'letter'),
                            Select::make('options.exercise_id')->label(fn ($get) => $get('type') === 'audio' ? 'Text zum Ablesen aus' : 'Zeigt die Antwort von')->native(false)->searchable()
                                ->options(fn () => Exercise::whereIn('unit_id', $this->getOwnerRecord()->units()->pluck('id'))->whereIn('type', Exercise::TEXTLIKE)->with('unit:id,title')->get()
                                    ->mapWithKeys(fn (Exercise $e) => [$e->id => $e->unit->title.': '.Str::limit($e->prompt ?: $e->title ?: Exercise::TYPES[$e->type], 50)])->all())
                                ->visible(fn ($get) => in_array($get('type'), ['mirror', 'audio'], true))->columnSpanFull(),
                            TextInput::make('options.tage')->label('Anzahl Tage')->numeric()->minValue(1)->maxValue(365)->placeholder('21')
                                ->visible(fn ($get) => $get('type') === 'practice'),
                            TextInput::make('options.aufgabe')->label('Tägliche Aufgabe')->placeholder('Deinen Brief laut lesen')->maxLength(160)
                                ->visible(fn ($get) => $get('type') === 'practice'),
                        ])->columns(2)->defaultItems(0)->addActionLabel('Übungsteil hinzufügen')->reorderableWithButtons()->collapsible()
                        ->itemLabel(fn (array $state) => ($state['title'] ?? null) ?: Str::limit($state['prompt'] ?? 'Übungsteil', 60)),
                ]),
            ])->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->columns([
                TextColumn::make('position')->label('#')->sortable(),
                TextColumn::make('title')->label('Titel')->searchable(),
                TextColumn::make('step.title')->label('Schritt')->sortable(),
                TextColumn::make('type')->label('Art')->badge()->formatStateUsing(fn (string $state) => Unit::TYPES[$state] ?? $state),
                TextColumn::make('exercises_count')->label('Teile')->counts('exercises'),
                IconColumn::make('is_published')->label('Öffentlich')->boolean(),
            ])
            ->defaultSort('position')
            ->reorderable('position')
            ->filters([
                SelectFilter::make('step_id')->label('Schritt')->options(fn () => $this->getOwnerRecord()->steps()->orderBy('position')->pluck('title', 'id')->all()),
                SelectFilter::make('type')->label('Art')->options(Unit::TYPES),
            ])
            ->headerActions([
                CreateAction::make()->label('Einheit anlegen'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}

<?php

namespace App\Filament\Coach\Resources\Questions;

use App\Filament\Coach\Resources\Questions\Pages\ListQuestions;
use App\Models\Question;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

/** Fragen aus dem Kursraum: Status setzen, "kommt in den Call" sammeln. Beantwortet wird im Kursraum. */
class QuestionResource extends Resource
{
    protected static ?string $model = Question::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQuestionMarkCircle;

    protected static ?string $modelLabel = 'Frage';

    protected static ?string $pluralModelLabel = 'Fragen';

    protected static ?string $navigationLabel = 'Fragen';

    protected static ?int $navigationSort = 25;

    public static function getNavigationBadge(): ?string
    {
        $n = Question::where('status', 'offen')->count();

        return $n ? (string) $n : null;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->label('Frage')->searchable()->wrap()
                    ->description(fn (Question $q) => $q->body ? Str::limit($q->body, 90) : null),
                TextColumn::make('user.name')->label('Von')->searchable(),
                TextColumn::make('program.title')->label('Kurs')->toggleable(),
                TextColumn::make('answers_count')->label('Antworten')->counts('answers')->sortable(),
                TextColumn::make('call_wuensche')->label('Callwunsch')
                    ->state(fn (Question $q) => $q->reactions()->where('emoji', Question::CALLWUNSCH)->count() ?: null),
                SelectColumn::make('status')->label('Status')->options(Question::STATUS)->selectablePlaceholder(false),
                TextColumn::make('visibility')->label('Sicht')->formatStateUsing(fn ($s) => $s === 'coach' ? 'Nur Coachin' : 'Kurs')->toggleable(),
                TextColumn::make('created_at')->label('Gestellt')->since()->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')->label('Status')->options(Question::STATUS)->default('offen'),
                SelectFilter::make('program_id')->label('Kurs')->relationship('program', 'title'),
            ])
            ->recordActions([
                Action::make('oeffnen')->label('Öffnen und antworten')->icon(Heroicon::OutlinedChatBubbleLeftRight)
                    ->url(fn (Question $q) => route('fragen.show', $q))->openUrlInNewTab(),
            ])
            ->toolbarActions([
                BulkAction::make('call')->label('Kommt in den Call')->icon(Heroicon::OutlinedVideoCamera)
                    ->action(fn (Collection $records) => $records->each->update(['status' => 'call'])),
                BulkAction::make('besprochen')->label('Im Call besprochen')->icon(Heroicon::OutlinedCheckCircle)
                    ->action(fn (Collection $records) => $records->each->update(['status' => 'besprochen'])),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListQuestions::route('/')];
    }
}

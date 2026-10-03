<?php

namespace App\Filament\Coach\Resources\Programs\Tables;

use App\Models\Program;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProgramsTable
{
    public static function configure(Table $table): Table
    {
        // 1:1-Begleitungen sind keine Programme: sie entstehen je Person und werden im Dossier eingerichtet.
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->where('type', '!=', 'one_on_one'))
            ->columns([
                TextColumn::make('title')->label('Titel')->searchable()->sortable()->description(fn (Program $r) => $r->subtitle),
                TextColumn::make('status')->label('Status')->badge()
                    ->state(fn (Program $r) => self::status($r))
                    ->formatStateUsing(fn (string $state) => self::STATUS[$state][0])
                    ->color(fn (string $state) => self::STATUS[$state][1])
                    ->description(fn (Program $r) => self::laufzeit($r)),
                TextColumn::make('type')->label('Art')->badge()->color('gray')->formatStateUsing(fn (string $state) => Program::TYPES[$state] ?? $state),
                TextColumn::make('pacing')->label('Taktung')->formatStateUsing(fn (string $state) => Program::PACINGS[$state] ?? $state)->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('steps_count')->label('Schritte')->counts('steps'),
                TextColumn::make('units_count')->label('Einheiten')->counts('units'),
                TextColumn::make('members_count')->label('Personen')->counts('members'),
                TextColumn::make('starts_at')->label('Start')->date('d.m.Y')->sortable()->toggleable(),
            ])
            ->defaultSort('position')
            ->reorderable('position')
            ->filters([
                SelectFilter::make('type')->label('Art')->options(array_diff_key(Program::TYPES, ['one_on_one' => 1])),
                SelectFilter::make('status')->label('Status')->options(array_map(fn ($s) => $s[0], self::STATUS))
                    ->query(fn (Builder $query, array $data) => match ($data['value'] ?? null) {
                        'offen' => $query->where('is_published', true)->where('is_internal', false),
                        'intern' => $query->where('is_internal', true),
                        'entwurf' => $query->where('is_published', false)->where('is_internal', false),
                        default => $query,
                    }),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }

    public const STATUS = [
        'offen' => ['Veröffentlicht', 'success'],
        'intern' => ['Nur intern', 'gray'],
        'entwurf' => ['Nicht veröffentlicht', 'warning'],
    ];

    /** Sichtbarkeit in einem Wort: veroeffentlicht fuer alle mit Zugang, nur intern, oder noch nicht veroeffentlicht. */
    public static function status(Program $r): string
    {
        return $r->is_internal ? 'intern' : ($r->is_published ? 'offen' : 'entwurf');
    }

    /** Laeuft, kommt noch oder ist vorbei, aus Start- und Enddatum. */
    public static function laufzeit(Program $r): ?string
    {
        if ($r->ends_at && $r->ends_at->isPast()) {
            return 'beendet '.$r->ends_at->format('d.m.Y');
        }
        if ($r->starts_at && $r->starts_at->isFuture()) {
            return 'startet '.$r->starts_at->format('d.m.Y');
        }
        if ($r->starts_at) {
            return 'läuft seit '.$r->starts_at->format('d.m.Y');
        }

        return null;
    }
}

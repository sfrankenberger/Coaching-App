<?php

namespace App\Filament\Coach\Resources\Programs\Tables;

use App\Models\Program;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ProgramsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->label('Titel')->searchable()->sortable()->description(fn (Program $r) => $r->subtitle),
                TextColumn::make('type')->label('Art')->badge()->formatStateUsing(fn (string $state) => Program::TYPES[$state] ?? $state),
                TextColumn::make('pacing')->label('Taktung')->formatStateUsing(fn (string $state) => Program::PACINGS[$state] ?? $state)->toggleable(),
                TextColumn::make('steps_count')->label('Schritte')->counts('steps'),
                TextColumn::make('units_count')->label('Einheiten')->counts('units'),
                TextColumn::make('members_count')->label('Personen')->counts('members'),
                TextColumn::make('starts_at')->label('Start')->date('d.m.Y')->sortable()->toggleable(),
                IconColumn::make('is_published')->label('Öffentlich')->boolean(),
                IconColumn::make('is_internal')->label('Intern')->boolean()->toggleable(),
            ])
            ->defaultSort('position')
            ->reorderable('position')
            ->filters([
                SelectFilter::make('type')->label('Art')->options(Program::TYPES),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}

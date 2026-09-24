<?php

namespace App\Filament\Coach\Resources\Memberships\Tables;

use App\Enums\Role;
use App\Filament\Coach\Resources\Memberships\MembershipResource;
use App\Models\Membership;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class MembershipsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')->label('Name')->searchable()->sortable(),
                TextColumn::make('user.email')->label('E-Mail')->searchable()->sortable()->copyable(),
                TextColumn::make('user.phone')->label('Telefon')->toggleable(),
                TextColumn::make('role')->label('Rolle')->badge()
                    ->formatStateUsing(fn (Role $state) => $state->label())
                    ->color(fn (Role $state) => $state->canManage() ? 'primary' : 'gray')
                    ->sortable(),
                TextColumn::make('status')->label('Status')->badge()
                    ->formatStateUsing(fn (string $state) => Membership::statusLabels()[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        'active' => 'success', 'paused' => 'warning', default => 'gray'
                    }),
                TextColumn::make('joined_at')->label('Dabei seit')->date('d.m.Y')->sortable()->toggleable(),
                TextColumn::make('legacy_id')->label('WP-ID')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('user.name')
            ->filters([
                SelectFilter::make('role')->label('Rolle')
                    ->options(collect(Role::cases())->mapWithKeys(fn (Role $r) => [$r->value => $r->label()])->all()),
                SelectFilter::make('status')->label('Status')->options(Membership::statusLabels()),
            ])
            ->recordActions([
                Action::make('dossier')->label('Dossier')->icon('heroicon-o-identification')
                    ->url(fn (Membership $record) => MembershipResource::getUrl('dossier', ['record' => $record])),
                EditAction::make(),
            ])
            ->recordUrl(fn (Membership $record) => MembershipResource::getUrl('dossier', ['record' => $record]));
    }
}

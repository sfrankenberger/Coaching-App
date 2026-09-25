<?php

namespace App\Filament\Coach\Resources\Memberships\Tables;

use App\Coach\Lage;
use App\Enums\Role;
use App\Filament\Coach\Resources\Memberships\MembershipResource;
use App\Models\Membership;
use App\Shop\Zugang;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

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
                TextColumn::make('lage')->label('Lage')->badge()
                    ->state(fn (Membership $record) => static::lage($record)['grund'] ?? null)
                    ->color(fn (Membership $record) => [1 => 'success', 2 => 'warning', 3 => 'danger'][static::lage($record)['stufe'] ?? 1])
                    ->toggleable(),
                TextColumn::make('last_seen_at')->label('Zuletzt da')->since()->sortable()->toggleable()->placeholder('noch nie'),
                TextColumn::make('naechster')->label('Nächster Termin')->toggleable()
                    ->state(fn (Membership $record) => (static::lage($record)['naechster'] ?? null)?->starts_at?->translatedFormat('D j.n. H:i')),
                TextColumn::make('aufgaben')->label('Aufgaben')->toggleable()
                    ->state(function (Membership $record) {
                        [$fertig, $gesamt] = static::lage($record)['aufgaben'] ?? [0, 0];

                        return $gesamt ? "{$fertig} von {$gesamt}" : null;
                    }),
                TextColumn::make('sitzungen')->label('Sitzungen offen')->toggleable(isToggledHiddenByDefault: true)
                    ->state(fn (Membership $record) => ($k = static::lage($record)['kontingent'] ?? null) ? $k['offen'].' von '.$k['gesamt'] : null),
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
            ->toolbarActions([
                BulkAction::make('einladen')->label('Einladung schicken')->icon('heroicon-o-envelope')->requiresConfirmation()
                    ->modalHeading('Willkommensmail mit Anmeldelink an die gewählten Personen?')
                    ->action(function (Collection $records) {
                        $n = 0;
                        foreach ($records as $m) {
                            if ($m->user && $m->status === 'active') {
                                app(Zugang::class)->welcome($m->user);
                                $n++;
                            }
                        }
                        Notification::make()->title("{$n} Einladung".($n === 1 ? '' : 'en').' geschickt')->success()->send();
                    })->deselectRecordsAfterCompletion(),
            ])
            ->recordUrl(fn (Membership $record) => MembershipResource::getUrl('dossier', ['record' => $record]));
    }

    /** Lage je Zeile, einmal pro Request berechnet (nur fuer begleitete Personen). */
    protected static function lage(Membership $record): array
    {
        if (! in_array($record->role, [Role::Member, Role::Client], true) || ! $record->user) {
            return [];
        }
        if (! app()->bound('coach.lage.zeilen')) {
            app()->instance('coach.lage.zeilen', new \ArrayObject(['wartet' => app(Lage::class)->wartende()]));
        }
        $cache = app('coach.lage.zeilen');

        return $cache[$record->getKey()] ??= app(Lage::class)->fuer($record, $cache['wartet']);
    }
}

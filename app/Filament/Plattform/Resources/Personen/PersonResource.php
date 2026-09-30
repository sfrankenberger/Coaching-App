<?php

namespace App\Filament\Plattform\Resources\Personen;

use App\Filament\Plattform\Resources\Personen\Pages\ListPersonen;
use App\Models\User;
use App\Support\Papierkorb\Papierkorb;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** Personen plattformweit: wer ist in welchen Mandanten, und "Person endgueltig loeschen". */
class PersonResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?string $modelLabel = 'Person';

    protected static ?string $pluralModelLabel = 'Personen';

    protected static ?string $navigationLabel = 'Personen';

    protected static ?string $slug = 'personen';

    protected static ?int $navigationSort = 20;

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('tenants'))
            ->columns([
                TextColumn::make('name')->label('Name')->searchable()->sortable(),
                TextColumn::make('email')->label('E-Mail')->searchable()->sortable()->copyable(),
                TextColumn::make('tenants.name')->label('Mandanten')->badge()->placeholder('keiner'),
                IconColumn::make('is_platform_admin')->label('Plattform-Admin')->boolean(),
                TextColumn::make('created_at')->label('Seit')->date('d.m.Y')->sortable()->toggleable(),
            ])
            ->defaultSort('name')
            ->recordActions([
                Action::make('endgueltigLoeschen')->label('Person endgültig löschen')->icon('heroicon-o-user-minus')->color('danger')
                    ->visible(fn (User $record) => ! $record->is_platform_admin)
                    ->modalHeading(fn (User $record) => $record->name.' endgültig löschen')
                    ->modalDescription('Die Person verschwindet aus allen Mandanten, mit allem, was ihr gehört: Zugänge, Antworten, Aufgaben, Notizen, Nachrichten, Buchungen. Auch der Papierkorb hilft danach nicht mehr. Das Änderungsprotokoll behält nur die Nummer.')
                    ->schema([
                        TextInput::make('bestaetigung')->label('Zur Bestätigung die E-Mail-Adresse der Person eintippen')->required()
                            ->rule(fn (User $record) => fn (string $attribute, $value, \Closure $fail) => trim((string) $value) === $record->email ?: $fail('Die Adresse stimmt nicht.')),
                    ])
                    ->modalSubmitActionLabel('Ja, endgültig löschen')
                    ->action(function (User $record) {
                        app(Papierkorb::class)->personLoeschen($record, auth()->user());
                        Notification::make()->title('Person endgültig gelöscht')->body($record->email)->warning()->send();
                    }),
            ]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPersonen::route('/'),
        ];
    }
}

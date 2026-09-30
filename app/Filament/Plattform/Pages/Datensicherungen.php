<?php

namespace App\Filament\Plattform\Pages;

use App\Support\Backup\BackupRestoreConfirmation;
use App\Support\Backup\BackupRestoreException;
use App\Support\Backup\BackupRestoreService;
use App\Support\Backup\Stand;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Gesicherte Staende der ganzen Installation (Datenbank und Dateien) ansehen und einen
 * davon wiederherstellen. Nur Plattform-Admins, mit dreifacher Bestaetigung.
 */
class Datensicherungen extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArchiveBox;

    protected static ?string $navigationLabel = 'Datensicherungen';

    protected static ?string $title = 'Datensicherungen';

    protected static ?int $navigationSort = 90;

    protected string $view = 'filament.plattform.datensicherungen';

    /** @var array{state: string, started_at: ?string, finished_at: ?string, snapshot: ?string, log: string, offline: bool} */
    public array $status = ['state' => 'idle', 'started_at' => null, 'finished_at' => null, 'snapshot' => null, 'log' => '', 'offline' => false];

    public ?string $fehler = null;

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->is_platform_admin;
    }

    public function mount(): void
    {
        $this->statusLaden();
    }

    public function eingerichtet(): bool
    {
        return app(BackupRestoreService::class)->eingerichtet();
    }

    /** @return Collection<int, array{zeit: CarbonImmutable, quellen: array<string, string>, vor_wiederherstellung: bool}> */
    public function staende(): Collection
    {
        $this->fehler = null;

        try {
            return app(BackupRestoreService::class)->zusammengefasst();
        } catch (BackupRestoreException $e) {
            $this->fehler = $e->getMessage();

            return collect();
        }
    }

    public function aktualisieren(): void
    {
        try {
            app(BackupRestoreService::class)->zusammengefasst(frisch: true);
        } catch (BackupRestoreException $e) {
            $this->fehler = $e->getMessage();
        }
        $this->statusLaden();
    }

    /** Wird alle paar Sekunden von der Seite aufgerufen (wire:poll). */
    public function statusLaden(): void
    {
        if (! $this->eingerichtet()) {
            return;
        }
        $this->status = app(BackupRestoreService::class)->status();
    }

    public function quellen(): array
    {
        return Stand::QUELLEN;
    }

    public function zuruecksetzenAction(): Action
    {
        $bestaetigung = app(BackupRestoreConfirmation::class);
        $user = auth()->user();

        return Action::make('zuruecksetzen')
            ->label('Wiederherstellen')
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->color('danger')
            ->size('sm')
            ->modalHeading('Stand wiederherstellen')
            ->modalIcon(Heroicon::OutlinedExclamationTriangle)
            ->modalIconColor('danger')
            ->modalDescription(fn (array $arguments) => 'Die ganze App (Datenbank und Dateien) wird auf den Stand vom '
                .$this->zeitLesbar($arguments['zeit'] ?? null).' zurückgesetzt. Alles, was seither passiert ist, geht verloren: '
                .'Anmeldungen, Antworten, Nachrichten, Buchungen, Einstellungen. Während der Wiederherstellung ist die App für alle offline. '
                .'Vorher wird automatisch ein Stand "vor Wiederherstellung" gesichert, mit dem du diesen Schritt rückgängig machen kannst.')
            ->modalSubmitActionLabel('Jetzt wiederherstellen')
            ->modalCancelActionLabel('Abbrechen')
            ->closeModalByClickingAway(false)
            ->mountUsing(function (Action $action, Schema $schema, array $arguments) use ($bestaetigung, $user) {
                if (app(BackupRestoreService::class)->laeuft()) {
                    Notification::make()->title('Es läuft bereits eine Wiederherstellung')->warning()->send();
                    $action->cancel();
                }
                $quellen = array_keys(array_filter($arguments['quellen'] ?? []));
                $schema->fill(['quelle' => in_array('local', $quellen, true) ? 'local' : ($quellen[0] ?? null)]);
                $bestaetigung->codeSenden($user);
                Notification::make()->title('Code geschickt')->body('Ein sechsstelliger Code ist unterwegs an '.$user->email.'.')->success()->send();
            })
            ->schema(fn (array $arguments) => [
                Radio::make('quelle')->label('Quelle')->required()
                    ->options(array_intersect_key(Stand::QUELLEN, array_filter($arguments['quellen'] ?? [])))
                    ->descriptions(['local' => 'Schneller, liegt auf demselben Server.', 'remote' => 'Kopie bei HiDrive, falls der Server selbst betroffen ist.'])
                    ->inline(),
                TextInput::make('code')->label('Code aus der Mail')->required()->autocomplete('one-time-code')->maxLength(6)->extraInputAttributes(['inputmode' => 'numeric']),
                TextInput::make('passwort')->label('Dein Passwort')->password()->revealable()->autocomplete('current-password')
                    ->visible($bestaetigung->passwortNoetig($user))->required($bestaetigung->passwortNoetig($user)),
                TextInput::make('app_name')->label('Zur Sicherheit den Namen der App abtippen')->required()->autocomplete('off')
                    ->placeholder($bestaetigung->erwarteterName()),
            ])
            ->action(function (array $data, array $arguments, Schema $schema) use ($bestaetigung, $user) {
                try {
                    $bestaetigung->pruefen($user, (string) ($data['code'] ?? ''), $data['passwort'] ?? null, (string) ($data['app_name'] ?? ''));
                } catch (ValidationException $e) {
                    // Fehler am richtigen Feld des Modals zeigen: Filament erwartet den vollen Statuspfad
                    $pfad = $schema->getStatePath();
                    throw ValidationException::withMessages(collect($e->errors())->mapWithKeys(fn ($m, $k) => [($pfad ? $pfad.'.' : '').$k => $m])->all());
                }

                $quelle = (string) ($data['quelle'] ?? '');
                $id = $arguments['quellen'][$quelle] ?? null;
                if (! is_string($id) || $id === '') {
                    Notification::make()->title('Für diese Quelle gibt es keinen Stand')->danger()->send();

                    return;
                }

                try {
                    app(BackupRestoreService::class)->wiederherstellen($id, $quelle, $user);
                } catch (BackupRestoreException $e) {
                    Notification::make()->title('Wiederherstellung nicht gestartet')->body($e->getMessage())->danger()->persistent()->send();

                    return;
                }

                Notification::make()->title('Wiederherstellung gestartet')
                    ->body('Die App geht gleich in den Wartungsmodus. Diese Seite zeigt den Fortschritt und meldet sich, wenn alles wieder da ist.')
                    ->success()->send();
                $this->statusLaden();
            });
    }

    public function zeitLesbar(?string $iso): string
    {
        if (! $iso) {
            return 'unbekannt';
        }

        return CarbonImmutable::parse($iso)->setTimezone((string) config('backup-restore.timezone'))->locale('de')->translatedFormat('l, j. F Y, H:i').' Uhr';
    }
}

<?php

use App\Enums\Role;
use App\Filament\Plattform\Pages\Datensicherungen;
use App\Mail\BackupRestoreCodeMail;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\CurrentTenant;
use Filament\Facades\Filament;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Process;
use Livewire\Livewire;

/** Attrappe der Schnittstelle mit zwei Laeufen; merkt sich die Aufrufe. */
function seiteVorbereiten(array &$aufrufe, string $zustand = 'idle'): void
{
    Process::fake(function (PendingProcess $p) use (&$aufrufe, $zustand) {
        $aufrufe[] = $p->command;

        return match ($p->command[3] ?? '') {
            'list' => Process::result(output: json_encode(['snapshots' => [
                ['id' => 'a0000001', 'time' => '2026-09-29T00:00:00Z', 'source' => 'local'],
                ['id' => 'b0000001', 'time' => '2026-09-29T00:00:01Z', 'source' => 'remote'],
                ['id' => 'b0000002', 'time' => '2026-09-27T22:15:00Z', 'source' => 'remote', 'tags' => ['pre-restore']],
            ]])),
            'status' => Process::result(output: json_encode(['state' => $zustand, 'log' => 'alles gut'])),
            'restore' => Process::result(output: '{"ok":true}'),
            default => Process::result(output: '', errorOutput: 'unbekannt', exitCode: 1),
        };
    });
}

beforeEach(function () {
    config()->set('backup-restore.command', 'sudo -n /usr/local/bin/app-restore');
    config()->set('backup-restore.app', 'lea');
    config()->set('backup-restore.timezone', 'Europe/Vienna');
    config()->set('app.name', 'Coaching-App');
    Cache::flush();

    $this->tenant = Tenant::create(['slug' => 'a', 'name' => 'A']);
    $this->tenant->domains()->create(['domain' => 'a.test', 'is_primary' => true]);
    $this->admin = User::factory()->create(['is_platform_admin' => true, 'password' => 'geheim123']);
    $this->tenant->users()->attach($this->admin, ['role' => Role::Owner->value, 'status' => 'active']);
});

/** Fuehrt einen Test im Plattform-Panel als angemeldete Person aus. */
function imPanel(Tenant $tenant, User $user, Closure $fn): mixed
{
    return app(CurrentTenant::class)->run($tenant, function () use ($user, $fn) {
        Filament::setCurrentPanel(Filament::getPanel('plattform'));
        test()->actingAs($user);

        return $fn();
    });
}

it('ist nur fuer Plattform-Admins da', function () {
    $aufrufe = [];
    seiteVorbereiten($aufrufe);
    $owner = User::factory()->create(['is_platform_admin' => false]);
    $this->tenant->users()->attach($owner, ['role' => Role::Owner->value, 'status' => 'active']);

    $this->actingAs($owner)->get('http://a.test/plattform/datensicherungen')->assertForbidden();
    $this->actingAs($this->admin)->get('http://a.test/plattform/datensicherungen')->assertOk();
    expect(Datensicherungen::canAccess())->toBeTrue();
});

it('zeigt die Staende zusammengefasst in Ortszeit mit Quelle und Kennzeichen', function () {
    $aufrufe = [];
    seiteVorbereiten($aufrufe);

    $this->actingAs($this->admin)->get('http://a.test/plattform/datensicherungen')
        ->assertOk()
        ->assertSeeInOrder(['Di., 29. September 2026, 02:00', 'Server', 'HiDrive', 'Mo., 28. September 2026, 00:15', 'HiDrive', 'Stand vor Wiederherstellung'])
        ->assertSee('Keine Wiederherstellung am Laufen')
        ->assertSee('Protokoll anzeigen')
        ->assertSee('alles gut');
});

it('sagt, wenn die Schnittstelle nicht eingerichtet ist, und ruft nichts auf', function () {
    config()->set('backup-restore.command', '');
    Process::fake();

    $this->actingAs($this->admin)->get('http://a.test/plattform/datensicherungen')
        ->assertOk()->assertSee('Nicht eingerichtet')->assertDontSee('Gesicherte Stände');
    Process::assertNothingRan();
});

it('zeigt den Fehler, wenn die Liste nicht kommt', function () {
    Process::fake(fn (PendingProcess $p) => ($p->command[3] ?? '') === 'status'
        ? Process::result(output: '', errorOutput: 'weg', exitCode: 1)
        : Process::result(output: '', errorOutput: 'Repository gesperrt', exitCode: 1));

    $this->actingAs($this->admin)->get('http://a.test/plattform/datensicherungen')
        ->assertOk()->assertSee('Repository gesperrt')->assertSee('antwortet gerade nicht');
});

it('schickt beim Oeffnen des Fensters einen Code und stellt mit richtigen Angaben wieder her', function () {
    $aufrufe = [];
    seiteVorbereiten($aufrufe);
    Mail::fake();

    imPanel($this->tenant, $this->admin, function () use (&$aufrufe) {
        $seite = Livewire::test(Datensicherungen::class)
            ->mountAction('zuruecksetzen', arguments: ['zeit' => '2026-09-29T02:00:00+02:00', 'quellen' => ['local' => 'a0000001', 'remote' => 'b0000001']])
            ->assertActionMounted('zuruecksetzen')
            ->assertActionDataSet(['quelle' => 'local']);

        $code = null;
        Mail::assertSent(BackupRestoreCodeMail::class, function (BackupRestoreCodeMail $m) use (&$code) {
            $code = $m->code;

            return $m->hasTo($this->admin->email);
        });

        $seite->setActionData(['quelle' => 'remote', 'code' => $code, 'passwort' => 'geheim123', 'app_name' => 'Coaching-App'])
            ->callMountedAction()
            ->assertHasNoActionErrors()
            ->assertNotified('Wiederherstellung gestartet');
    });

    $restore = collect($aufrufe)->first(fn ($a) => ($a[3] ?? '') === 'restore');
    expect($restore)->toBe(['sudo', '-n', '/usr/local/bin/app-restore', 'restore', 'lea', 'b0000001', 'remote']);
});

it('lehnt falschen Code, falsches Passwort und falschen Namen ab und startet nichts', function () {
    $aufrufe = [];
    seiteVorbereiten($aufrufe);
    Mail::fake();

    imPanel($this->tenant, $this->admin, function () {
        Livewire::test(Datensicherungen::class)
            ->callAction('zuruecksetzen', data: ['quelle' => 'local', 'code' => '000000', 'passwort' => 'falsch', 'app_name' => 'Falsch'],
                arguments: ['zeit' => '2026-09-29T02:00:00+02:00', 'quellen' => ['local' => 'a0000001']])
            ->assertHasActionErrors(['code', 'passwort', 'app_name']);
    });

    expect(collect($aufrufe)->contains(fn ($a) => ($a[3] ?? '') === 'restore'))->toBeFalse();
});

it('oeffnet das Fenster nicht, wenn schon eine Wiederherstellung laeuft', function () {
    $aufrufe = [];
    seiteVorbereiten($aufrufe, 'running');
    Mail::fake();

    imPanel($this->tenant, $this->admin, function () {
        Livewire::test(Datensicherungen::class)
            ->mountAction('zuruecksetzen', arguments: ['zeit' => '2026-09-29T02:00:00+02:00', 'quellen' => ['local' => 'a0000001']])
            ->assertActionNotMounted('zuruecksetzen')
            ->assertNotified('Es läuft bereits eine Wiederherstellung');
    });
    Mail::assertNothingSent();
});

it('zeigt den laufenden Zustand beim Nachfragen', function () {
    $aufrufe = [];
    seiteVorbereiten($aufrufe, 'running');

    imPanel($this->tenant, $this->admin, function () {
        Livewire::test(Datensicherungen::class)
            ->call('statusLaden')
            ->assertSet('status.state', 'running')
            ->assertSee('Die App ist offline');
    });
});

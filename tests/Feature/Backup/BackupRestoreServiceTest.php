<?php

use App\Models\User;
use App\Support\Backup\BackupRestoreException;
use App\Support\Backup\BackupRestoreService;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

/**
 * Baut eine Attrappe der Server-Schnittstelle: je Unterbefehl eine Antwort (Array = JSON, String = rohe Ausgabe, int = Exit-Code).
 *
 * @return array<int, list<string>> Liste der tatsaechlichen Aufrufe (per Referenz gefuellt)
 */
function schnittstelle(array $antworten, array &$aufrufe = []): void
{
    Process::fake(function (PendingProcess $p) use ($antworten, &$aufrufe) {
        $befehl = is_array($p->command) ? $p->command : preg_split('/\s+/', $p->command);
        $aufrufe[] = $befehl;
        $unter = $befehl[3] ?? '';
        $antwort = $antworten[$unter] ?? 1;

        return match (true) {
            is_int($antwort) => Process::result(output: '', errorOutput: 'kaputt', exitCode: $antwort),
            is_array($antwort) => Process::result(output: json_encode($antwort)),
            default => Process::result(output: (string) $antwort),
        };
    });
}

beforeEach(function () {
    config()->set('backup-restore.command', 'sudo -n /usr/local/bin/app-restore');
    config()->set('backup-restore.app', 'lea');
    config()->set('backup-restore.cache_seconds', 60);
    config()->set('backup-restore.timezone', 'Europe/Vienna');
    Cache::flush();
});

it('ruft die Schnittstelle mit sudo, Unterbefehl und App-Namen auf', function () {
    $aufrufe = [];
    schnittstelle(['list' => ['snapshots' => []]], $aufrufe);

    app(BackupRestoreService::class)->staende();

    expect($aufrufe)->toBe([['sudo', '-n', '/usr/local/bin/app-restore', 'list', 'lea']]);
});

it('liest Staende tolerant und sortiert die neuesten nach oben', function () {
    schnittstelle(['list' => ['snapshots' => [
        ['id' => 'aa11bb22', 'time' => '2026-09-28T02:00:01+02:00', 'source' => 'local'],
        ['short_id' => 'CC33DD44', 'time' => '2026-09-29T02:00:03+02:00', 'repo' => 'hidrive', 'tags' => ['pre-restore']],
        ['id' => 'nicht-hex', 'time' => '2026-09-29T02:00:03+02:00'],
        ['id' => 'ee55ff66', 'time' => 'kein datum'],
        'unsinn',
    ]]]);

    $staende = app(BackupRestoreService::class)->staende();

    expect($staende)->toHaveCount(2)
        ->and($staende[0]->id)->toBe('cc33dd44')
        ->and($staende[0]->quelle)->toBe('remote')
        ->and($staende[0]->quelleName())->toBe('HiDrive')
        ->and($staende[0]->vorWiederherstellung)->toBeTrue()
        ->and($staende[1]->id)->toBe('aa11bb22')
        ->and($staende[1]->quelle)->toBe('local')
        ->and($staende[1]->vorWiederherstellung)->toBeFalse();
});

it('nimmt auch eine nackte Liste als Antwort', function () {
    schnittstelle(['list' => [['id' => 'aa11bb22', 'time' => '2026-09-28T02:00:01+02:00']]]);

    expect(app(BackupRestoreService::class)->staende())->toHaveCount(1);
});

it('fasst Server und HiDrive aus demselben Lauf zusammen, entfernte Laeufe nicht', function () {
    config()->set('backup-restore.merge_seconds', 90);
    schnittstelle(['list' => ['snapshots' => [
        ['id' => 'a0000001', 'time' => '2026-09-29T00:00:00Z', 'source' => 'local'],
        ['id' => 'b0000001', 'time' => '2026-09-29T00:00:01Z', 'source' => 'remote'],
        ['id' => 'a0000002', 'time' => '2026-09-28T00:00:00Z', 'source' => 'local'],
        ['id' => 'b0000002', 'time' => '2026-09-28T00:02:00Z', 'source' => 'remote', 'pre_restore' => true],
        ['id' => 'a0000003', 'time' => '2026-09-27T00:00:00Z', 'source' => 'local'],
        ['id' => 'a0000004', 'time' => '2026-09-27T00:00:30Z', 'source' => 'local'],
    ]]]);

    $gruppen = app(BackupRestoreService::class)->zusammengefasst();

    expect($gruppen)->toHaveCount(5)
        ->and($gruppen[0]['quellen'])->toBe(['local' => 'a0000001', 'remote' => 'b0000001'])
        ->and($gruppen[0]['zeit']->format('Y-m-d H:i T'))->toBe('2026-09-29 02:00 CEST')
        ->and($gruppen[1]['quellen'])->toBe(['remote' => 'b0000002'])
        ->and($gruppen[1]['vor_wiederherstellung'])->toBeTrue()
        ->and($gruppen[2]['quellen'])->toBe(['local' => 'a0000002'])
        // zwei lokale Staende kurz nacheinander bleiben getrennt, dieselbe Quelle wird nie zusammengelegt
        ->and($gruppen[3]['quellen'])->toBe(['local' => 'a0000004'])
        ->and($gruppen[4]['quellen'])->toBe(['local' => 'a0000003']);
});

it('speichert die Liste kurz zwischen und laedt auf Wunsch frisch', function () {
    $aufrufe = [];
    schnittstelle(['list' => ['snapshots' => []]], $aufrufe);
    $dienst = app(BackupRestoreService::class);

    $dienst->staende();
    $dienst->staende();
    expect($aufrufe)->toHaveCount(1);

    $dienst->staende(frisch: true);
    expect($aufrufe)->toHaveCount(2);
});

it('meldet einen Fehler, wenn die Schnittstelle scheitert oder kein JSON liefert', function () {
    schnittstelle(['list' => 1]);
    expect(fn () => app(BackupRestoreService::class)->staende())->toThrow(BackupRestoreException::class, 'kaputt');

    Cache::flush();
    schnittstelle(['list' => 'das ist kein json']);
    expect(fn () => app(BackupRestoreService::class)->staende())->toThrow(BackupRestoreException::class, 'kein JSON');

    Cache::flush();
    schnittstelle(['list' => ['foo' => 'bar']]);
    expect(fn () => app(BackupRestoreService::class)->staende())->toThrow(BackupRestoreException::class, 'unerwartetes Format');
});

it('ist ohne Befehl nicht eingerichtet und ruft nichts auf', function () {
    config()->set('backup-restore.command', '');
    Process::fake();

    $dienst = app(BackupRestoreService::class);
    expect($dienst->eingerichtet())->toBeFalse()
        ->and(fn () => $dienst->staende())->toThrow(BackupRestoreException::class, 'nicht eingerichtet');
    Process::assertNothingRan();
});

it('liest den Status und rechnet Zeiten in die Anzeigezone um', function () {
    schnittstelle(['status' => ['state' => 'RUNNING', 'started_at' => '2026-09-29T00:00:00Z', 'snapshot' => 'a0000001', 'log' => ['Zeile 1', 'Zeile 2']]]);

    $status = app(BackupRestoreService::class)->status();

    expect($status['state'])->toBe('running')
        ->and($status['offline'])->toBeFalse()
        ->and($status['started_at'])->toBe('2026-09-29T02:00:00+02:00')
        ->and($status['finished_at'])->toBeNull()
        ->and($status['snapshot'])->toBe('a0000001')
        ->and($status['log'])->toBe("Zeile 1\nZeile 2");
});

it('setzt unbekannte Zustaende auf idle', function () {
    schnittstelle(['status' => ['status' => 'irgendwas']]);
    expect(app(BackupRestoreService::class)->status()['state'])->toBe('idle');

    schnittstelle(['status' => '']);
    expect(app(BackupRestoreService::class)->status()['state'])->toBe('idle');
});

it('ist offline, wenn der Status nicht abrufbar ist', function () {
    schnittstelle(['status' => 1]);

    $status = app(BackupRestoreService::class)->status();

    expect($status['offline'])->toBeTrue()->and($status['state'])->toBe('idle');
});

it('startet die Wiederherstellung, protokolliert sie vorher und leert den Zwischenspeicher', function () {
    $aufrufe = [];
    schnittstelle(['status' => ['state' => 'idle'], 'restore' => ['ok' => true], 'list' => ['snapshots' => []]], $aufrufe);
    Log::shouldReceive('channel')->with('backup-restore')->once()->andReturnSelf();
    Log::shouldReceive('warning')->once()->withArgs(function (string $text, array $kontext) {
        return $text === 'Wiederherstellung angestossen'
            && $kontext['snapshot'] === 'a0000001' && $kontext['quelle'] === 'remote' && $kontext['von'] === 'admin@example.com';
    });
    $user = User::factory()->create(['email' => 'admin@example.com']);
    $dienst = app(BackupRestoreService::class);
    $dienst->staende();

    $dienst->wiederherstellen('A0000001', 'remote', $user);

    expect(end($aufrufe))->toBe(['sudo', '-n', '/usr/local/bin/app-restore', 'restore', 'lea', 'a0000001', 'remote'])
        ->and(Cache::has('backup-restore:staende:lea'))->toBeFalse();
});

it('weist ungueltige Kennungen und Quellen ab, ohne den Server zu fragen', function () {
    Process::fake();
    $user = User::factory()->create();
    $dienst = app(BackupRestoreService::class);

    expect(fn () => $dienst->wiederherstellen('../etc', 'local', $user))->toThrow(BackupRestoreException::class, 'Kennung')
        ->and(fn () => $dienst->wiederherstellen('a0000001', 'usb', $user))->toThrow(BackupRestoreException::class, 'Quelle');
    Process::assertNothingRan();
});

it('startet nichts, solange eine Wiederherstellung laeuft', function () {
    $aufrufe = [];
    schnittstelle(['status' => ['state' => 'running']], $aufrufe);

    expect(fn () => app(BackupRestoreService::class)->wiederherstellen('a0000001', 'local', User::factory()->create()))
        ->toThrow(BackupRestoreException::class, 'bereits');
    expect(array_column($aufrufe, 3))->toBe(['status']);
});

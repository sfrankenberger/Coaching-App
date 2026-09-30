<?php

namespace App\Support\Backup;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

/**
 * Ruft die Server-Schnittstelle fuer Datensicherungen auf (siehe config/backup-restore.php).
 * Die App sichert nichts selbst, sie zeigt die Staende und stoesst die Wiederherstellung an.
 */
class BackupRestoreService
{
    public const ZUSTAENDE = ['idle', 'running', 'done', 'failed'];

    public function eingerichtet(): bool
    {
        return trim((string) config('backup-restore.command')) !== '';
    }

    /** Alle Staende, neueste zuerst. Kurz zwischengespeichert, damit die Seite nicht bei jedem Klick den Server fragt. */
    public function staende(bool $frisch = false): Collection
    {
        $schluessel = 'backup-restore:staende:'.config('backup-restore.app');
        if ($frisch) {
            Cache::forget($schluessel);
        }

        $zeilen = Cache::remember($schluessel, max(0, (int) config('backup-restore.cache_seconds')), function () {
            $antwort = $this->aufrufen(['list']);
            $liste = $antwort['snapshots'] ?? $antwort['staende'] ?? (array_is_list($antwort) ? $antwort : null);
            if (! is_array($liste)) {
                throw new BackupRestoreException('Die Liste der Staende hat ein unerwartetes Format.');
            }

            return array_values(array_filter(array_map(fn ($z) => is_array($z) ? $z : null, $liste)));
        });

        return collect($zeilen)
            ->map(fn (array $z) => Stand::ausZeile($z))
            ->filter()
            ->sortByDesc(fn (Stand $s) => $s->zeit->getTimestamp())
            ->values();
    }

    /**
     * Staende zusammengefasst: lokal und entfernt aus demselben Lauf werden ein Eintrag.
     *
     * @return Collection<int, array{zeit: CarbonImmutable, quellen: array<string, string>, vor_wiederherstellung: bool}>
     */
    public function zusammengefasst(bool $frisch = false): Collection
    {
        $toleranz = max(0, (int) config('backup-restore.merge_seconds'));
        $zeitzone = (string) config('backup-restore.timezone');
        $gruppen = [];

        foreach ($this->staende($frisch) as $stand) {
            foreach ($gruppen as &$g) {
                if (! isset($g['quellen'][$stand->quelle]) && abs($g['zeit']->getTimestamp() - $stand->zeit->getTimestamp()) <= $toleranz) {
                    $g['quellen'][$stand->quelle] = $stand->id;
                    $g['vor_wiederherstellung'] = $g['vor_wiederherstellung'] || $stand->vorWiederherstellung;

                    continue 2;
                }
            }
            unset($g);

            $gruppen[] = [
                'zeit' => $stand->zeit->setTimezone($zeitzone),
                'quellen' => [$stand->quelle => $stand->id],
                'vor_wiederherstellung' => $stand->vorWiederherstellung,
            ];
        }

        return collect($gruppen)->map(function (array $g) {
            ksort($g['quellen']);

            return $g;
        });
    }

    /**
     * Stand der Wiederherstellung. Antwortet die Schnittstelle nicht, ist "offline" gesetzt.
     *
     * @return array{state: string, started_at: ?string, finished_at: ?string, snapshot: ?string, log: string, offline: bool}
     */
    public function status(): array
    {
        $leer = ['state' => 'idle', 'started_at' => null, 'finished_at' => null, 'snapshot' => null, 'log' => '', 'offline' => false];

        try {
            $antwort = $this->aufrufen(['status'], 15);
        } catch (BackupRestoreException $e) {
            return ['offline' => true, 'log' => $e->getMessage()] + $leer;
        }

        $state = strtolower((string) ($antwort['state'] ?? $antwort['status'] ?? 'idle'));
        $log = $antwort['log'] ?? '';

        return [
            'state' => in_array($state, self::ZUSTAENDE, true) ? $state : 'idle',
            'started_at' => $this->zeitLesbar($antwort['started_at'] ?? null),
            'finished_at' => $this->zeitLesbar($antwort['finished_at'] ?? null),
            'snapshot' => is_string($antwort['snapshot'] ?? null) ? $antwort['snapshot'] : null,
            'log' => is_array($log) ? implode("\n", array_map('strval', $log)) : (string) $log,
            'offline' => false,
        ];
    }

    public function laeuft(): bool
    {
        return $this->status()['state'] === 'running';
    }

    /** Stoesst die Wiederherstellung an. Schreibt vorher ins Log, wer was wann angestossen hat. */
    public function wiederherstellen(string $id, string $quelle, User $von): void
    {
        $id = strtolower(trim($id));
        if (! preg_match('/^[0-9a-f]{6,64}$/', $id)) {
            throw new BackupRestoreException('Ungueltige Kennung des Standes.');
        }
        if (! array_key_exists($quelle, Stand::QUELLEN)) {
            throw new BackupRestoreException('Ungueltige Quelle.');
        }
        if ($this->laeuft()) {
            throw new BackupRestoreException('Es laeuft bereits eine Wiederherstellung.');
        }

        Log::channel('backup-restore')->warning('Wiederherstellung angestossen', [
            'app' => config('backup-restore.app'),
            'snapshot' => $id,
            'quelle' => $quelle,
            'von' => $von->email,
            'user_id' => $von->id,
            'ip' => request()?->ip(),
        ]);

        $this->aufrufen(['restore', $id, $quelle], 60);
        Cache::forget('backup-restore:staende:'.config('backup-restore.app'));
    }

    /**
     * Fuehrt einen Unterbefehl aus und liefert das JSON als Array.
     *
     * @param  list<string>  $argumente  Unterbefehl und weitere Argumente, der App-Name wird nach dem Unterbefehl eingefuegt
     */
    protected function aufrufen(array $argumente, int $timeout = 30): array
    {
        if (! $this->eingerichtet()) {
            throw new BackupRestoreException('Die Datensicherung ist auf diesem Server nicht eingerichtet.');
        }

        $befehl = preg_split('/\s+/', trim((string) config('backup-restore.command')), -1, PREG_SPLIT_NO_EMPTY);
        $unterbefehl = array_shift($argumente);
        $kommando = [...$befehl, $unterbefehl, (string) config('backup-restore.app'), ...$argumente];

        try {
            $ergebnis = Process::timeout($timeout)->run($kommando);
        } catch (\Throwable $e) {
            throw new BackupRestoreException('Die Server-Schnittstelle konnte nicht aufgerufen werden: '.$e->getMessage(), previous: $e);
        }

        return $this->auswerten($ergebnis, $unterbefehl);
    }

    protected function auswerten(ProcessResult $ergebnis, string $unterbefehl): array
    {
        if (! $ergebnis->successful()) {
            $fehler = trim($ergebnis->errorOutput()) ?: trim($ergebnis->output()) ?: 'Exit-Code '.$ergebnis->exitCode();

            throw new BackupRestoreException("Die Server-Schnittstelle meldet einen Fehler ({$unterbefehl}): ".$fehler);
        }

        $ausgabe = trim($ergebnis->output());
        if ($ausgabe === '') {
            return [];
        }

        $daten = json_decode($ausgabe, true);
        if (! is_array($daten)) {
            throw new BackupRestoreException("Die Antwort der Server-Schnittstelle ({$unterbefehl}) ist kein JSON.");
        }

        return $daten;
    }

    protected function zeitLesbar(mixed $wert): ?string
    {
        if (! is_string($wert) || $wert === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($wert)->setTimezone((string) config('backup-restore.timezone'))->toIso8601String();
        } catch (\Throwable) {
            return null;
        }
    }
}

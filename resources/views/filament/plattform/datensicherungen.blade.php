<x-filament-panels::page>
    <style>
        .ds-tabelle { width: 100%; border-collapse: collapse; font-size: .875rem; }
        .ds-tabelle th, .ds-tabelle td { padding: .625rem .75rem; text-align: left; border-bottom: 1px solid rgb(229 231 235); vertical-align: middle; }
        .ds-tabelle th { font-weight: 600; color: rgb(107 114 128); font-size: .75rem; text-transform: uppercase; letter-spacing: .04em; }
        .ds-tabelle tr:last-child td { border-bottom: 0; }
        .ds-quellen { display: flex; gap: .375rem; flex-wrap: wrap; }
        .ds-status { display: flex; align-items: center; gap: .75rem; flex-wrap: wrap; }
        .ds-punkt { width: .75rem; height: .75rem; border-radius: 9999px; flex: none; }
        .ds-punkt-idle { background: rgb(156 163 175); }
        .ds-punkt-running { background: rgb(245 158 11); animation: ds-blink 1s infinite; }
        .ds-punkt-done { background: rgb(22 163 74); }
        .ds-punkt-failed { background: rgb(220 38 38); }
        .ds-punkt-offline { background: rgb(217 119 6); animation: ds-blink 1s infinite; }
        @keyframes ds-blink { 50% { opacity: .3; } }
        .ds-log { margin-top: .5rem; padding: .75rem; background: rgb(17 24 39); color: rgb(229 231 235); border-radius: .5rem; font-size: .75rem; line-height: 1.5; white-space: pre-wrap; max-height: 20rem; overflow: auto; }
        .ds-hinweis { color: rgb(107 114 128); font-size: .875rem; }
        .ds-warnung { border: 1px solid rgb(251 191 36); background: rgb(255 251 235); color: rgb(120 53 15); border-radius: .5rem; padding: .75rem 1rem; }
        .ds-fehler { border: 1px solid rgb(252 165 165); background: rgb(254 242 242); color: rgb(153 27 27); border-radius: .5rem; padding: .75rem 1rem; }
        .ds-tabelle td.ds-zeit { white-space: nowrap; font-weight: 600; }
    </style>

    @if (! $this->eingerichtet())
        <div class="ds-warnung">
            <strong>Nicht eingerichtet.</strong> Auf diesem Server fehlt die Schnittstelle zur Datensicherung (<code>BACKUP_RESTORE_COMMAND</code> in der <code>.env</code>). Die Sicherungen selbst laufen unabhängig von der App auf dem Server.
        </div>
    @else
        {{-- Stand der Wiederherstellung, fragt alle 3 Sekunden nach --}}
        <x-filament::section wire:poll.3s="statusLaden" id="ds-status">
            <x-slot name="heading">Wiederherstellung</x-slot>
            @php $s = $this->status; @endphp
            <div class="ds-status" data-zustand="{{ $s['offline'] ? 'offline' : $s['state'] }}">
                <span class="ds-punkt ds-punkt-{{ $s['offline'] ? 'offline' : $s['state'] }}"></span>
                @if ($s['offline'])
                    <span>Die Schnittstelle antwortet gerade nicht. Läuft eine Wiederherstellung, ist das normal: die App ist dann im Wartungsmodus.</span>
                @elseif ($s['state'] === 'running')
                    <span><strong>Läuft</strong>{{ $s['started_at'] ? ', seit '.$this->zeitLesbar($s['started_at']) : '' }}{{ $s['snapshot'] ? ', Stand '.$s['snapshot'] : '' }}. Die App ist offline, bitte nichts anfassen.</span>
                @elseif ($s['state'] === 'done')
                    <span><strong>Fertig</strong>{{ $s['finished_at'] ? ' am '.$this->zeitLesbar($s['finished_at']) : '' }}{{ $s['snapshot'] ? ', Stand '.$s['snapshot'] : '' }}. Die App läuft wieder.</span>
                @elseif ($s['state'] === 'failed')
                    <span><strong>Fehlgeschlagen</strong>{{ $s['finished_at'] ? ' am '.$this->zeitLesbar($s['finished_at']) : '' }}. Bitte das Protokoll lesen und Sebastian Bescheid geben.</span>
                @else
                    <span>Keine Wiederherstellung am Laufen.</span>
                @endif
            </div>
            <div id="ds-offline" class="ds-warnung" style="display:none;margin-top:.75rem">
                Der Server ist im Wartungsmodus, die Wiederherstellung läuft. Diese Seite lädt sich neu, sobald die App wieder erreichbar ist.
            </div>
            @if (trim($s['log']) !== '')
                <details style="margin-top:.5rem">
                    <summary class="ds-hinweis" style="cursor:pointer">Protokoll anzeigen</summary>
                    <pre class="ds-log">{{ $s['log'] }}</pre>
                </details>
            @endif
        </x-filament::section>

        @php $staende = $this->staende(); @endphp

        <x-filament::section>
            <x-slot name="heading">Gesicherte Stände</x-slot>
            <x-slot name="description">Datenbank und Dateien der ganzen Installation. Zeiten in {{ config('backup-restore.timezone') }}. Server und HiDrive aus demselben Lauf stehen in einer Zeile.</x-slot>
            <x-slot name="headerEnd">
                <x-filament::button wire:click="aktualisieren" color="gray" size="sm" icon="heroicon-o-arrow-path">Neu laden</x-filament::button>
            </x-slot>

            @if ($this->fehler)
                <div class="ds-fehler">{{ $this->fehler }}</div>
            @elseif ($staende->isEmpty())
                <p class="ds-hinweis">Noch keine Stände vorhanden.</p>
            @else
                <table class="ds-tabelle">
                    <thead>
                        <tr>
                            <th>Datum und Uhrzeit</th>
                            <th>Quelle</th>
                            <th></th>
                            <th style="text-align:right"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($staende as $stand)
                            <tr>
                                <td class="ds-zeit">{{ $stand['zeit']->locale('de')->translatedFormat('D, j. F Y, H:i') }} Uhr</td>
                                <td>
                                    <div class="ds-quellen">
                                        @foreach ($stand['quellen'] as $quelle => $id)
                                            <x-filament::badge :color="$quelle === 'local' ? 'gray' : 'info'" :title="$id">{{ $this->quellen()[$quelle] ?? $quelle }}</x-filament::badge>
                                        @endforeach
                                    </div>
                                </td>
                                <td>
                                    @if ($stand['vor_wiederherstellung'])
                                        <x-filament::badge color="warning" icon="heroicon-o-shield-check">Stand vor Wiederherstellung</x-filament::badge>
                                    @endif
                                </td>
                                <td style="text-align:right">
                                    {{ ($this->zuruecksetzenAction)(['zeit' => $stand['zeit']->toIso8601String(), 'quellen' => $stand['quellen']]) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </x-filament::section>

        <p class="ds-hinweis">Vor jeder Wiederherstellung sichert der Server den aktuellen Stand als "Stand vor Wiederherstellung". Damit lässt sich ein Fehlgriff rückgängig machen. Jede Wiederherstellung wird mit Person und Zeit in <code>storage/logs/backup-restore.log</code> festgehalten.</p>

        <script>
            // Waehrend der Wiederherstellung antwortet der Server mit 503 (Wartungsmodus).
            // Livewire soll dann keinen Fehler zeigen, sondern die Seite meldet es und laedt neu, sobald die App zurueck ist.
            document.addEventListener('livewire:init', () => {
                let wartend = false;
                const zurueckPruefen = () => {
                    fetch(window.location.href, { cache: 'no-store', credentials: 'same-origin' })
                        .then(r => { if (r.status === 503) { setTimeout(zurueckPruefen, 5000); } else { window.location.reload(); } })
                        .catch(() => setTimeout(zurueckPruefen, 5000));
                };
                Livewire.hook('request', ({ fail }) => {
                    fail(({ status, preventDefault }) => {
                        if (status !== 503) return;
                        preventDefault();
                        const box = document.getElementById('ds-offline');
                        if (box) box.style.display = '';
                        if (! wartend) { wartend = true; setTimeout(zurueckPruefen, 5000); }
                    });
                });
            });
        </script>
    @endif
</x-filament-panels::page>

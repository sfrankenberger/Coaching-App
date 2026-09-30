<x-filament-panels::page>
    <style>
        .pk-tabelle { width: 100%; border-collapse: collapse; font-size: .875rem; }
        .pk-tabelle th, .pk-tabelle td { padding: .5rem .75rem; text-align: left; border-bottom: 1px solid rgb(229 231 235); vertical-align: middle; }
        .pk-tabelle th { font-weight: 600; color: rgb(107 114 128); font-size: .75rem; text-transform: uppercase; letter-spacing: .04em; }
        .pk-tabelle tr:last-child td { border-bottom: 0; }
        .pk-hinweis { color: rgb(107 114 128); font-size: .875rem; }
        .pk-knoepfe { display: flex; gap: .375rem; justify-content: flex-end; flex-wrap: wrap; }
        .pk-kopf { display: flex; gap: .75rem; align-items: center; flex-wrap: wrap; margin-bottom: 1rem; }
        .pk-kopf select { font-size: .875rem; border: 1px solid rgb(209 213 219); border-radius: .5rem; padding: .375rem 2rem .375rem .75rem; }
    </style>

    @php $zeilen = $this->zeilen(); @endphp

    <x-filament::section>
        <x-slot name="heading">Gelöschtes</x-slot>
        <x-slot name="description">Was hier liegt, ist für alle unsichtbar und kommt mit einem Klick zurück. Nach {{ $this->fristTage() }} Tagen wird es endgültig gelöscht.</x-slot>

        <div class="pk-kopf">
            <label class="pk-hinweis" for="pk-typ">Anzeigen:</label>
            <select id="pk-typ" wire:model.live="typ">
                <option value="">Alles</option>
                @foreach ($this->typen() as $alias => $name)
                    <option value="{{ $alias }}">{{ $name }}</option>
                @endforeach
            </select>
            <span class="pk-hinweis">{{ $zeilen->count() }} {{ $zeilen->count() === 1 ? 'Eintrag' : 'Einträge' }}</span>
        </div>

        @if ($zeilen->isEmpty())
            <p class="pk-hinweis">Der Papierkorb ist leer.</p>
        @else
            <table class="pk-tabelle">
                <thead>
                    <tr>
                        <th>Was</th>
                        <th>Titel</th>
                        <th>Person</th>
                        @if ($this->istPlattform())<th>Mandant</th>@endif
                        <th>Gelöscht</th>
                        <th>Von</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($zeilen as $z)
                        <tr wire:key="pk-{{ $z['typ'] }}-{{ $z['id'] }}">
                            <td><x-filament::badge color="gray">{{ $z['typName'] }}</x-filament::badge></td>
                            <td>{{ $z['titel'] }}</td>
                            <td>{{ $z['person'] }}</td>
                            @if ($this->istPlattform())<td>{{ $z['mandant'] }}</td>@endif
                            <td title="Endgültig weg am {{ $z['endgueltigAm']->format('d.m.Y') }}">{{ $z['geloeschtAm']->format('d.m.Y H:i') }}</td>
                            <td>{{ $z['von'] }}</td>
                            <td>
                                <div class="pk-knoepfe">
                                    <x-filament::button size="sm" color="gray" icon="heroicon-o-arrow-uturn-left" wire:click="wiederherstellen('{{ $z['typ'] }}', {{ $z['id'] }})">Wiederherstellen</x-filament::button>
                                    @if ($this->istPlattform())
                                        <x-filament::button size="sm" color="danger" icon="heroicon-o-x-mark" wire:click="endgueltig('{{ $z['typ'] }}', {{ $z['id'] }})" wire:confirm="Endgültig löschen? Das lässt sich nicht rückgängig machen. Alles, was daran hängt, geht mit.">Endgültig löschen</x-filament::button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </x-filament::section>

    @unless ($this->istPlattform())
        <p class="pk-hinweis">Endgültiges Löschen macht nur die Plattform-Verwaltung. Was älter als {{ $this->fristTage() }} Tage ist, verschwindet von selbst.</p>
    @endunless
</x-filament-panels::page>

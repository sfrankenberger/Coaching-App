<x-filament-panels::page>
    @php $stand = $this->stand(); @endphp
    @if (session('meldung'))<div class="rounded border p-3 mb-4">{{ session('meldung') }}</div>@endif
    @if (session('fehler'))<div class="rounded border p-3 mb-4 text-danger-600">{{ session('fehler') }}</div>@endif

    <div class="rounded border p-4" style="margin-bottom:16px">
        @if ($stand['verbunden'])
            <p style="margin:0"><strong>Verbunden</strong>{{ $stand['firma'] ? ' mit '.$stand['firma'] : '' }}{{ $stand['seit'] ? ', seit '.\Carbon\Carbon::parse($stand['seit'])->translatedFormat('j. F Y') : '' }} ({{ $stand['art'] === 'token' ? 'fester Token' : 'dauerhafte Verbindung' }}).</p>
            @if ($stand['fehler'])<p class="text-gray-500" style="margin:6px 0 0">Letzter Fehler: {{ $stand['fehler'] }}</p>@endif
            <div style="margin-top:10px;display:flex;gap:8px;flex-wrap:wrap">
                <x-filament::button tag="a" :href="$stand['start']" color="gray" size="sm">Neu verbinden</x-filament::button>
                <x-filament::button wire:click="trennen" wire:confirm="Verbindung zu bexio trennen?" color="danger" size="sm">Trennen</x-filament::button>
            </div>
        @else
            <p style="margin:0"><strong>Noch nicht verbunden.</strong> Speichere Client-ID und Client-Secret, dann "Mit bexio verbinden". Oder trage einen festen Zugriffstoken ein.</p>
            <p class="text-gray-500" style="margin:6px 0 0">Im bexio-Entwicklerportal muss diese Rückkehr-Adresse eingetragen sein: <code>{{ $stand['rueckkehr'] }}</code></p>
        @endif
    </div>

    <form wire:submit="speichern">
        {{ $this->form }}
        <div class="mt-6" style="display:flex;gap:8px;flex-wrap:wrap">
            <x-filament::button type="submit">Speichern</x-filament::button>
            <x-filament::button tag="a" :href="$stand['start']" color="gray">Mit bexio verbinden</x-filament::button>
        </div>
    </form>
</x-filament-panels::page>

<x-filament-panels::page>
    <form wire:submit="speichern">
        {{ $this->form }}
        <div class="mt-6" style="display:flex;gap:8px;flex-wrap:wrap">
            <x-filament::button type="submit">Speichern</x-filament::button>
            @if (static::class === \App\Filament\Coach\Pages\Verbindungen::class)
                <x-filament::button wire:click="testMail" color="gray">Test-Mail an mich schicken</x-filament::button>
            @endif
        </div>
    </form>
</x-filament-panels::page>

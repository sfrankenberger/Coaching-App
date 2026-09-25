<x-filament-panels::page>
    <form wire:submit="speichern">
        {{ $this->form }}
        <div class="mt-6">
            <x-filament::button type="submit">Speichern</x-filament::button>
        </div>
    </form>
</x-filament-panels::page>

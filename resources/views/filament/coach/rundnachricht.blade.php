<x-filament-panels::page>
    <form wire:submit="senden">
        {{ $this->form }}
        <div class="mt-6 flex items-center gap-3">
            <x-filament::button type="submit" icon="heroicon-o-paper-airplane">Senden</x-filament::button>
            <span class="text-sm text-gray-500">Geht sofort raus. Team und du selbst bekommen sie nicht.</span>
        </div>
    </form>
</x-filament-panels::page>

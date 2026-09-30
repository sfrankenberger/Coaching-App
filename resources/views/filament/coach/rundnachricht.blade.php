<x-filament-panels::page>
    <form wire:submit.prevent="mountAction('senden')">
        {{ $this->form }}
        <div class="mt-6 flex items-center gap-3">
            {{ $this->sendenAction }}
            <span class="text-sm text-gray-500">Vor dem Senden fragt die App nochmals nach. Team und du selbst bekommen sie nicht.</span>
        </div>
    </form>
</x-filament-panels::page>

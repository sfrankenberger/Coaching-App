<x-filament-panels::page>
    <form wire:submit.prevent="mountAction('senden')">
        {{ $this->form }}
        <div class="mt-6 flex flex-wrap items-center gap-3">
            {{ $this->sendenAction }}
            {{ $this->entwurfAction }}
            {{ $this->testAction }}
            @if ($entwurfId)<span class="text-sm text-gray-500">Entwurf geladen. <button type="button" wire:click="neu" class="underline">Neu anfangen</button></span>
            @else<span class="text-sm text-gray-500">Vor dem Senden fragt die App nochmals nach. Team und du selbst bekommen sie nicht.</span>@endif
        </div>
    </form>

    @if ($this->entwuerfe->isNotEmpty())
        <x-filament::section heading="Entwürfe" class="mt-8">
            <table class="w-full text-sm">
                @foreach ($this->entwuerfe as $e)
                    <tr class="border-b border-gray-100 {{ $entwurfId === $e->id ? 'font-semibold' : '' }}">
                        <td class="py-2 pr-3">{{ $e->titel ?: \Illuminate\Support\Str::limit($e->text, 60) }}</td>
                        <td class="py-2 pr-3 text-gray-500">{{ $e->wohin() }}</td>
                        <td class="py-2 pr-3 text-gray-500">{{ $e->user?->name }}, {{ $e->updated_at->format('d.m.Y H:i') }}</td>
                        <td class="py-2 text-right whitespace-nowrap">
                            <button type="button" wire:click="laden({{ $e->id }})" class="underline">Laden</button>
                            <button type="button" wire:click="loeschen({{ $e->id }})" wire:confirm="Entwurf löschen?" class="ml-3 underline text-gray-500">Löschen</button>
                        </td>
                    </tr>
                @endforeach
            </table>
        </x-filament::section>
    @endif

    <x-filament::section heading="Verschickt" class="mt-8">
        @if ($this->verschickte->isEmpty())
            <p class="text-sm text-gray-500">Noch keine Rundnachricht verschickt.</p>
        @else
            <table class="w-full text-sm">
                <thead><tr class="text-left text-gray-500"><th class="py-1 pr-3">Wann</th><th class="py-1 pr-3">Titel</th><th class="py-1 pr-3">An</th><th class="py-1 pr-3">Erreicht</th><th class="py-1 pr-3">Von</th><th></th></tr></thead>
                @foreach ($this->verschickte as $e)
                    <tr class="border-b border-gray-100">
                        <td class="py-2 pr-3 whitespace-nowrap">{{ $e->sent_at?->format('d.m.Y H:i') }}</td>
                        <td class="py-2 pr-3">{{ $e->titel ?: \Illuminate\Support\Str::limit($e->text, 60) }}</td>
                        <td class="py-2 pr-3 text-gray-500">{{ $e->wohin() }}{{ $e->persoenlich ? ', persönlich' : '' }}</td>
                        <td class="py-2 pr-3 text-gray-500">{{ $e->erreicht }} von {{ $e->empfaenger }}</td>
                        <td class="py-2 pr-3 text-gray-500">{{ $e->user?->name }}</td>
                        <td class="py-2 text-right"><button type="button" wire:click="laden({{ $e->id }})" class="underline">Als Vorlage</button></td>
                    </tr>
                @endforeach
            </table>
        @endif
    </x-filament::section>
</x-filament-panels::page>

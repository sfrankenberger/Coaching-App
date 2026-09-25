{{-- Kommentarfuss unter einem geteilten Eintrag im Dossier --}}
@php $schluessel = $typ.'-'.$item->id; @endphp
<div class="mt-2 space-y-1.5">
    @foreach ($item->comments->sortBy('created_at') as $c)
        <div @class(['rounded-lg px-3 py-1.5 text-sm', 'bg-primary-50 dark:bg-primary-500/10' => $c->user_id !== $item->user_id, 'bg-gray-50 dark:bg-white/5' => $c->user_id === $item->user_id])>
            <span class="text-xs text-gray-500">{{ $c->user?->vorname() }} · {{ $c->created_at->format('d.m. H:i') }}</span>
            <div class="whitespace-pre-line">{{ $c->body }}</div>
        </div>
    @endforeach
    <form wire:submit="antworten('{{ $typ }}', {{ $item->id }})" class="flex gap-2">
        <input type="text" wire:model="antwort.{{ $schluessel }}" placeholder="Antworten ..." class="flex-1 rounded-lg border-gray-200 px-3 py-1.5 text-sm dark:border-white/10 dark:bg-white/5">
        <x-filament::button type="submit" size="sm" color="gray" icon="heroicon-o-paper-airplane">Senden</x-filament::button>
    </form>
</div>

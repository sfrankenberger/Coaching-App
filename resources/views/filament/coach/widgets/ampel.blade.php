<x-filament-widgets::widget>
    <x-filament::section :heading="$wartend ? ($wartend === 1 ? 'Eine Person wartet auf deine Antwort' : $wartend.' Personen warten auf deine Antwort') : 'Wer braucht dich gerade?'"
        :description="$gruen.' von '.$gesamt.' sind gut unterwegs.'">
        @if ($zeilen->isEmpty())
            <p class="text-sm text-gray-500">Alle sind gut unterwegs. Niemand wartet.</p>
        @else
            <div class="divide-y divide-gray-100 dark:divide-white/5">
                @foreach ($zeilen as $z)
                    <div class="flex flex-wrap items-center gap-3 py-2.5">
                        <span @class(['h-3 w-3 shrink-0 rounded-full', 'bg-danger-500' => $z['farbe'] === 'rot', 'bg-warning-400' => $z['farbe'] === 'gelb'])></span>
                        <a href="{{ $z['dossier'] }}" class="font-medium text-sm hover:underline">{{ $z['user']->name }}</a>
                        <span class="text-sm text-gray-600 dark:text-gray-400">{{ $z['grund'] }}@if ($z['wartet']) (seit {{ $z['wartet']->diffForHumans(null, true) }})@endif</span>
                        <span class="ms-auto flex gap-2">
                            @if ($z['kontingent'])<x-filament::badge color="gray">{{ $z['kontingent']['offen'] }} Sitzungen offen</x-filament::badge>@endif
                            <x-filament::link :href="$z['nachfragen']" icon="heroicon-o-chat-bubble-left-ellipsis" size="sm">{{ $z['wartet'] ? 'Antworten' : 'Kurz nachfragen' }}</x-filament::link>
                        </span>
                    </div>
                @endforeach
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>

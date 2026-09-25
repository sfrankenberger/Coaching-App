<x-filament-widgets::widget>
    <x-filament::section heading="Wochencheck" :description="'Kalenderwoche '.$kw" collapsible>
        <div class="space-y-5">
            @if ($haken)
                <ul class="space-y-1.5">
                    @foreach ($haken as $k => [$text, $von])
                        <li>
                            <label class="flex items-start gap-2 text-sm">
                                <input type="checkbox" class="mt-0.5 rounded" @checked($von) wire:click="haken('{{ $k }}', {{ $von ? 'false' : 'true' }})">
                                <span @class(['text-gray-400 line-through' => $von])>{{ $text }}</span>
                            </label>
                        </li>
                    @endforeach
                </ul>
            @endif
            @foreach ($programme as $eintrag)
                <div>
                    <div class="text-sm font-semibold">{{ $eintrag['program']->title }}</div>
                    @foreach ($eintrag['wochen'] as $woche)
                        <div class="mt-2 text-xs uppercase tracking-wide text-gray-500">{{ $woche['titel'] }}</div>
                        <ul class="mt-1 space-y-1">
                            @foreach ($woche['zeilen'] as [$ok, $text, $link, $linktext])
                                <li class="flex items-start gap-2 text-sm">
                                    <x-filament::icon :icon="$ok === null ? 'heroicon-o-information-circle' : ($ok ? 'heroicon-o-check-circle' : 'heroicon-o-exclamation-triangle')"
                                        @class(['mt-0.5 h-4 w-4 shrink-0', 'text-gray-400' => $ok === null, 'text-success-600' => $ok === true, 'text-warning-600' => $ok === false]) />
                                    <span @class(['flex-1', 'font-medium' => $ok === false])>{{ $text }}</span>
                                    @if ($link)<x-filament::link :href="$link" size="sm">{{ $linktext }}</x-filament::link>@endif
                                </li>
                            @endforeach
                        </ul>
                    @endforeach
                </div>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-widgets::widget>

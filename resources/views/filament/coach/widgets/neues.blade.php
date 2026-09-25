<x-filament-widgets::widget>
    <div class="grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2">
            <x-filament::section heading="Neu von den Personen" description="Die letzten sieben Tage">
                @forelse ($zeilen as $z)
                    <div class="flex items-start gap-3 py-2 border-b border-gray-100 last:border-0">
                        <span class="w-24 shrink-0 text-xs text-gray-500 tabular-nums">{{ $z['zeit']?->translatedFormat('D, j. M H:i') }}</span>
                        <span class="min-w-0 flex-1 text-sm">
                            @if ($z['url'])<a href="{{ $z['url'] }}" class="font-semibold text-primary-600 hover:underline">{{ $z['wer'] }}</a>@else<b>{{ $z['wer'] }}</b>@endif
                            {{ $z['was'] }}@if ($z['detail']): <span class="text-gray-600">{{ $z['detail'] }}</span>@endif
                        </span>
                    </div>
                @empty
                    <p class="text-sm text-gray-500">Diese Woche ist noch nichts angekommen.</p>
                @endforelse
            </x-filament::section>
        </div>
        <div>
            <x-filament::section heading="Die nächsten zwei Wochen">
                @forelse ($termine as $e)
                    <div class="py-2 border-b border-gray-100 last:border-0 text-sm">
                        <span class="block text-xs text-gray-500">{{ $e->starts_at->translatedFormat('D, j. M') }}{{ $e->all_day ? '' : ', '.$e->starts_at->format('H:i') }}</span>
                        <a href="{{ \App\Filament\Coach\Resources\Events\EventResource::getUrl('edit', ['record' => $e]) }}" class="font-semibold hover:underline">{{ $e->title }}</a>
                        <span class="block text-xs text-gray-600">{{ $e->user?->name ?? $e->program?->title }}</span>
                    </div>
                @empty
                    <p class="text-sm text-gray-500">Kein Termin geplant.</p>
                @endforelse
            </x-filament::section>
        </div>
    </div>
</x-filament-widgets::widget>

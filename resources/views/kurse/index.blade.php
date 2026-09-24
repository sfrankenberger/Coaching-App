<x-layouts.app title="Meine Kurse">
    <h1 class="mb-3">Meine Kurse</h1>

    @forelse ($programs as $program)
        @php $stand = $program->stand; @endphp
        <a href="{{ route('kurse.show', $program) }}" class="karte block no-underline text-ink hover:border-primary" style="--kc: {{ $program->color ?: 'var(--c-primary)' }}">
            <div class="flex items-start gap-3">
                <span class="mt-1 size-10 shrink-0 rounded-xl grid place-items-center text-primary-contrast" style="background: var(--kc)">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="size-5"><path d="M2 8l10-4 10 4-10 4z"/><path d="M6 10v5c0 1.5 3 3 6 3s6-1.5 6-3v-5"/></svg>
                </span>
                <div class="min-w-0 flex-1">
                    <span class="hinweis uppercase tracking-wider text-xs font-semibold">{{ $program->typeLabel() }}</span>
                    <h2 class="text-lg leading-snug">{{ $program->title }}</h2>
                    @if ($program->subtitle)
                        <p class="text-ink-soft text-md mt-1">{{ $program->subtitle }}</p>
                    @endif
                    @if ($stand['total'])
                        <div class="mt-3 flex items-center gap-3">
                            <span class="h-1.5 flex-1 rounded-full bg-line overflow-hidden"><span class="block h-full rounded-full" style="width: {{ $stand['percent'] }}%; background: var(--kc)"></span></span>
                            <span class="hinweis whitespace-nowrap">{{ $stand['done'] }} von {{ $stand['total'] }}</span>
                        </div>
                    @endif
                </div>
            </div>
        </a>
    @empty
        <x-karte>
            <p class="text-ink-soft">Noch kein Kurs für dich freigeschaltet. Sobald es losgeht, siehst du ihn hier.</p>
        </x-karte>
    @endforelse
</x-layouts.app>

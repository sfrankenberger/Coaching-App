@forelse ($programme as $p)
    @php $st = $p->stand; $pm = $p->mitglied; @endphp
    <div class="karte">
        <div class="flex items-start gap-2">
            <div class="min-w-0 flex-1">
                <b class="t">{{ $p->title }}</b>
                <span class="hinweis block">{{ $st['done'] ?? 0 }} von {{ $st['total'] ?? 0 }} Einheiten{{ $pm?->joined_at ? ' · dabei seit '.\App\Support\Zeit::datum($pm->joined_at) : '' }}{{ $pm?->last_seen_at ? ' · zuletzt '.\App\Support\Zeit::relativ($pm->last_seen_at) : '' }}</span>
                @if (($st['total'] ?? 0) > 0)
                    <div class="balken balken-duenn mt-2"><span style="width:{{ round(($st['done'] ?? 0) / $st['total'] * 100) }}%"></span></div>
                @endif
            </div>
        </div>
        <div class="flex gap-2 mt-2 flex-wrap">
            <a href="{{ route('kurse.show', $p) }}" class="knopf knopf-leise knopf-klein">Kursraum</a>
            <a href="/coach/programs/{{ $p->id }}/edit" class="knopf knopf-text knopf-klein">Einrichten</a>
        </div>
    </div>
@empty
    <x-leer icon="graduation-cap">{{ $person->vorname() }} ist in keinem Kurs. Oben unter Pakete kannst du einen freischalten.</x-leer>
@endforelse

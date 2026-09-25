<x-layouts.app title="Meine Kurse">
    <h1>Meine Kurse</h1>

    @php
        $gruppen = $programs->groupBy(fn ($p) => $p->type === 'one_on_one' ? 'einzel' : ($p->isWorkbook() ? 'buch' : 'kurs'));
        $titel = ['kurs' => ['graduation-cap', 'Kurse'], 'buch' => ['book', 'Arbeitsbücher'], 'einzel' => ['user', 'Begleitungen']];
    @endphp

    @forelse (['kurs', 'buch', 'einzel'] as $g)
        @continue(! $gruppen->has($g))
        @if ($gruppen->count() > 1)
            <h2 class="abschnitt"><i class="fa-solid fa-{{ $titel[$g][0] }}"></i>{{ $titel[$g][1] }}<em>{{ $gruppen[$g]->count() }}</em></h2>
        @endif
        @foreach ($gruppen[$g] as $program)
            @php $stand = $program->stand; $icon = $program->icon ?: ($g === 'einzel' ? 'user' : ($g === 'buch' ? 'book-open' : 'seedling')); @endphp
            <a href="{{ route('kurse.show', $program) }}" class="karte kurs-karte" style="--kc: {{ $program->color ?: '#7C8C9A' }}">
                @if ($program->cover_url)
                    <span class="kurs-bild" style="background-image:url('{{ $program->cover_url }}')"></span>
                @else
                    <span class="kurs-bild kurs-bild-farbe"><i class="fa-solid fa-{{ $icon }}"></i></span>
                @endif
                <span class="kurs-text">
                    <span class="eyebrow">{{ $program->typeLabel() }}</span>
                    <span class="kurs-titel">{{ $program->title }}</span>
                    @if ($program->subtitle)<span class="x block" style="margin-top:4px">{{ \Illuminate\Support\Str::limit($program->subtitle, 110) }}</span>@endif
                    @if ($stand['total'])
                        <span class="flex items-center gap-3" style="margin-top:12px">
                            <span class="balken"><span style="width: {{ $stand['percent'] }}%"></span></span>
                            <span class="balken-label">{{ $stand['done'] }} von {{ $stand['total'] }}</span>
                        </span>
                    @endif
                </span>
            </a>
        @endforeach
    @empty
        <div class="leer"><i class="fa-solid fa-graduation-cap"></i>Noch kein Kurs für dich freigeschaltet. Sobald es losgeht, siehst du ihn hier.</div>
    @endforelse
</x-layouts.app>

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
            @php $stand = $program->stand; @endphp
            @if ($program->cover_url && $g !== 'einzel')
                <a href="{{ route('kurse.show', $program) }}" class="karte kurs-karte" style="--kc: {{ $program->color ?: '#7C8C9A' }}">
                    <span class="kurs-bild" style="background-image:url('{{ $program->cover_url }}')"></span>
                    <span class="kurs-text">
                        <span class="eyebrow">{{ $program->typeLabel() }}</span>
                        <span class="block" style="font-family:var(--font-heading);font-size:var(--fs-xl);line-height:1.25;margin-top:2px">{{ $program->title }}</span>
                        @if ($program->subtitle)<span class="x block" style="margin-top:4px">{{ $program->subtitle }}</span>@endif
                        @if ($stand['total'])
                            <span class="flex items-center gap-3" style="margin-top:12px">
                                <span class="balken"><span style="width: {{ $stand['percent'] }}%"></span></span>
                                <span class="balken-label">{{ $stand['done'] }} von {{ $stand['total'] }}</span>
                            </span>
                        @endif
                    </span>
                </a>
            @else
                <a href="{{ route('kurse.show', $program) }}" class="zeile" style="--kc: {{ $program->color ?: '#7C8C9A' }}">
                    <span class="ic" style="background:color-mix(in srgb, var(--kc) 16%, #fff);color:var(--kc)"><i class="fa-solid fa-{{ $program->icon ?: ($g === 'einzel' ? 'user' : 'seedling') }}"></i></span>
                    <span class="tx">
                        <b>{{ $program->title }}</b>
                        <span>{{ $program->subtitle ?: $program->typeLabel() }}</span>
                        @if ($stand['total'])
                            <span class="flex items-center gap-3" style="margin-top:8px;white-space:normal">
                                <span class="balken"><span style="width: {{ $stand['percent'] }}%"></span></span>
                                <span class="balken-label">{{ $stand['done'] }} von {{ $stand['total'] }}</span>
                            </span>
                        @endif
                    </span>
                    <i class="fa-solid fa-chevron-right pf"></i>
                </a>
            @endif
        @endforeach
    @empty
        <div class="leer"><i class="fa-solid fa-graduation-cap"></i>Noch kein Kurs für dich freigeschaltet. Sobald es losgeht, siehst du ihn hier.</div>
    @endforelse
</x-layouts.app>

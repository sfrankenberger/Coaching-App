<x-layouts.app title="Meine Kurse">
    <h1>Meine Kurse</h1>

    @forelse ($programs as $program)
        @php $stand = $program->stand; @endphp
        <a href="{{ route('kurse.show', $program) }}" class="karte kurs-karte" style="--kc: {{ $program->color ?: '#7C8C9A' }}">
            @if ($program->cover_url)
                <span class="kurs-bild" style="background-image:url('{{ $program->cover_url }}')"></span>
            @else
                <span class="kurs-bild kurs-bild-farbe"><i class="fa-solid fa-{{ $program->icon ?: 'seedling' }}"></i></span>
            @endif
            <span class="kurs-text">
                <span class="eyebrow">{{ $program->typeLabel() }}</span>
                <span class="t" style="font-family:var(--font-heading);font-weight:400;font-size:var(--fs-xl);line-height:1.25;margin-top:2px">{{ $program->title }}</span>
                @if ($program->subtitle)<span class="x block" style="margin-top:4px">{{ $program->subtitle }}</span>@endif
                @if ($stand['total'])
                    <span class="flex items-center gap-3" style="margin-top:12px">
                        <span class="balken"><span style="width: {{ $stand['percent'] }}%"></span></span>
                        <span class="balken-label">{{ $stand['done'] }} von {{ $stand['total'] }}</span>
                    </span>
                @endif
            </span>
        </a>
    @empty
        <div class="leer"><i class="fa-solid fa-graduation-cap"></i>Noch kein Kurs für dich freigeschaltet. Sobald es losgeht, siehst du ihn hier.</div>
    @endforelse
</x-layouts.app>

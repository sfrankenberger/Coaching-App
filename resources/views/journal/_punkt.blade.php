@php $item = $x['item']; $art = $x['art']; [$label, $icon] = \App\Programs\Zeitleiste::ARTEN[$art]; $ich = auth()->id(); @endphp
@if ($art === 'aufgabe')
    @include('aufgaben._karte', ['t' => $item, 'ohneKommentare' => $item->user_id !== $ich])
@elseif ($art === 'termin' || $art === 'aufzeichnung')
    <x-termin-karte :termin="$item" :etikett="$label" />
@else
    <article class="karte" @if ($item->projekt ?? null) style="border-left:4px solid {{ $item->projekt->farbe }}" @endif>
        <div class="flex items-start gap-3">
            <span class="zl-ic"><i class="fa-solid fa-{{ $icon }}"></i></span>
            <div class="min-w-0 flex-1">
                @if ($art === 'reflexion')
                    <span class="t">{{ $item->week_label ?: 'Reflexion' }}</span>
                    @foreach (\App\Http\Controllers\ReflexionController::FRAGEN as $k => [$ico, $frage])
                        @if ($item->$k)<p class="mt-1 mb-0"><b class="block text-md">{{ $ico }} {{ $frage }}</b><span class="lesetext whitespace-pre-line">{{ $item->$k }}</span></p>@endif
                    @endforeach
                @else
                    @if ($item->title)<span class="t">{{ $item->title }}</span>@endif
                    @if ($item->body)<p class="lesetext whitespace-pre-line m-0 mt-1">{{ $item->body }}</p>@endif
                    @if ($art === 'journal' && $item->url)<a href="{{ $item->url }}" target="_blank" rel="noopener" class="text-md">Link</a>@endif
                @endif
                <span class="m mt-2">
                    {{ $label }} · {{ $x['zeit']->translatedFormat('D, j. M') }}
                    @if ($item->program) · {{ $item->program->title }} @endif
                    @if ($item->user_id !== $ich) · von {{ $item->user?->vorname() }} @endif
                    @if ($item->projekt ?? null) · <x-projekt-chip :projekt="$item->projekt" /> @endif
                </span>
                @if ($art !== 'journal')<x-anhaenge :item="$item" /><x-kommentare :item="$item" />@endif
            </div>
        </div>
    </article>
@endif

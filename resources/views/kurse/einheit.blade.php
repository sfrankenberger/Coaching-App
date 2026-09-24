<x-layouts.app :title="$unit->title">
    @php $videos = $unit->videoList(); $teile = $unit->answerableExercises(); @endphp
    <p class="mb-2">
        <a href="{{ $unit->step ? route('kurse.schritt', [$program, $unit->step]) : route('kurse.show', $program) }}" class="hinweis no-underline">&larr; {{ $unit->step?->title ?? $program->title }}</a>
    </p>

    <x-karte>
        <span class="hinweis uppercase tracking-wider text-xs font-semibold">
            {{ \App\Models\Unit::TYPES[$unit->type] ?? '' }}@if ($nummer) · {{ $nummer }} von {{ $anzahl }}@endif
            @if ($unit->is_core) · Kern @endif
        </span>
        <h1>{{ $unit->title }}</h1>
        @if ($unit->intro)
            <p class="text-ink-soft mt-2 whitespace-pre-line">{{ $unit->intro }}</p>
        @endif
    </x-karte>

    @if ($videos)
        @php $erstes = \App\Support\Video::embed($videos[0]['url']); @endphp
        <x-karte class="!p-2">
            @if ($erstes)
                <div class="video" id="video-player">
                    @if ($erstes['kind'] === 'iframe')
                        <iframe src="{{ $erstes['src'] }}" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen loading="lazy" title="Video"></iframe>
                    @else
                        <video controls preload="metadata" src="{{ $erstes['src'] }}"></video>
                    @endif
                </div>
            @else
                <a href="{{ $videos[0]['url'] }}" target="_blank" rel="noopener" class="knopf knopf-leise m-2">Video öffnen</a>
            @endif
            @if (count($videos) > 1)
                <ol class="mt-2 divide-y divide-line">
                    @foreach ($videos as $i => $v)
                        @php $e = \App\Support\Video::embed($v['url']); @endphp
                        <li>
                            <button type="button" class="video-wahl flex w-full items-center gap-3 px-3 py-2 text-left {{ $i === 0 ? 'text-primary font-semibold' : '' }}" data-src="{{ $e['src'] ?? '' }}" data-kind="{{ $e['kind'] ?? '' }}">
                                <span class="size-6 shrink-0 rounded-full bg-line grid place-items-center text-xs">{{ $i + 1 }}</span>
                                <span class="text-md">{{ $v['title'] ?: 'Video '.($i + 1) }}</span>
                            </button>
                        </li>
                    @endforeach
                </ol>
            @endif
        </x-karte>
    @endif

    @if ($unit->body)
        <x-karte><div class="prose-app">{!! $unit->body !!}</div></x-karte>
    @endif

    @if ($unit->links)
        <x-karte titel="Links">
            <ul class="space-y-1">
                @foreach ($unit->links as $l)
                    @if (filled($l['url'] ?? null))
                        <li><a href="{{ $l['url'] }}" target="_blank" rel="noopener">{{ $l['title'] ?: $l['url'] }}</a></li>
                    @endif
                @endforeach
            </ul>
        </x-karte>
    @endif

    @if ($unit->exercises->isNotEmpty())
        <x-karte id="uebung" data-uebung>
            <div class="flex items-center justify-between gap-3 mb-2">
                <h2>{{ $unit->type === 'exercise_set' ? 'Deine Antworten' : 'Zum Mitmachen' }}</h2>
                @if ($uebung['total'])
                    <span class="hinweis"><span data-uebung-voll>{{ $uebung['filled'] }}</span> von {{ $uebung['total'] }}</span>
                @endif
            </div>
            @foreach ($unit->exercises as $ex)
                @php $a = $answers->get($ex->id); $v = $a?->value['v'] ?? null; @endphp
                @switch($ex->type)
                    @case('heading')
                        <h3 class="mt-4 mb-1 border-b border-line pb-1">{{ $ex->title ?: $ex->prompt }}</h3>
                        @break
                    @case('hint')
                        <p class="font-heading italic text-ink-soft mb-3">{{ $ex->prompt }}</p>
                        @break
                    @case('text')
                    @case('note')
                        <div class="mb-3">
                            <label for="ex-{{ $ex->id }}" class="feld-label font-normal text-ink">{{ $ex->prompt ?: $ex->title }}</label>
                            <div class="relative">
                                <textarea id="ex-{{ $ex->id }}" class="feld" rows="{{ $ex->type === 'note' ? 6 : 3 }}" placeholder="Schreib hier ..." data-antwort="{{ $ex->id }}">{{ is_array($v) ? implode(', ', $v) : $v }}</textarea>
                                <span class="absolute right-3 bottom-2 text-xs text-muted" data-status></span>
                            </div>
                        </div>
                        @break
                    @case('scale')
                        <div class="mb-4" data-antwort-skala="{{ $ex->id }}">
                            <span class="feld-label font-normal text-ink">{{ rtrim($ex->prompt ?: $ex->title, ':') }}</span>
                            <div class="flex flex-wrap gap-1">
                                @for ($n = 1; $n <= 10; $n++)
                                    <button type="button" data-wert="{{ $n }}" @class(['size-8 rounded-lg border text-sm', 'bg-primary text-primary-contrast border-primary' => (int) $v === $n, 'border-line bg-page' => (int) $v !== $n])>{{ $n }}</button>
                                @endfor
                            </div>
                        </div>
                        @break
                    @case('values')
                    @case('choice')
                        @php $gewaehlt = (array) $v; @endphp
                        <div class="mb-4" data-antwort-werte="{{ $ex->id }}" data-einzeln="{{ $ex->type === 'choice' ? 1 : 0 }}">
                            <span class="feld-label font-normal text-ink">{{ $ex->prompt ?: $ex->title }}</span>
                            <div class="flex flex-wrap gap-2">
                                @foreach ((array) ($ex->options['values'] ?? []) as $w)
                                    <button type="button" data-wert="{{ $w }}" @class(['rounded-full border px-3 py-1.5 text-sm', 'bg-primary text-primary-contrast border-primary' => in_array($w, $gewaehlt, true), 'border-line bg-page' => ! in_array($w, $gewaehlt, true)])>{{ $w }}</button>
                                @endforeach
                            </div>
                            <p class="hinweis mt-1"><span data-zahl>{{ count($gewaehlt) }}</span> ausgewählt</p>
                        </div>
                        @break
                    @case('checkbox')
                        <label class="flex items-start gap-3 py-2">
                            <input type="checkbox" class="mt-1 size-5 accent-primary" data-antwort-haken="{{ $ex->id }}" @checked($v)>
                            <span class="text-base {{ $v ? 'text-muted line-through' : '' }}">{{ $ex->prompt ?: $ex->title }}</span>
                        </label>
                        @break
                @endswitch
            @endforeach

            @if ($teile->isNotEmpty() && ! auth()->user()->canManageCurrentTenant())
                <div class="mt-4 border-t border-line pt-3 flex flex-wrap items-center gap-3">
                    <form method="post" action="{{ route('kurse.teilen', [$program, $unit]) }}" data-teilen>
                        @csrf
                        <input type="hidden" name="an" value="{{ $geteilt ? 0 : 1 }}">
                        <button type="submit" @class(['knopf', 'knopf-leise' => ! $geteilt])>{{ $geteilt ? 'Mit deiner Coachin geteilt' : 'Mit deiner Coachin teilen' }}</button>
                    </form>
                    <span class="hinweis">{{ $shareMode === 'alles' ? 'Du teilst grundsätzlich alles. Hier kannst du eine Ausnahme machen.' : 'Nur was du teilst, sieht deine Coachin.' }}</span>
                </div>
            @endif
        </x-karte>
    @endif

    <x-karte titel="Deine Notiz dazu">
        <form method="post" action="{{ route('kurse.notiz', [$program, $unit]) }}" class="eingabe" data-notiz>
            @csrf
            <div class="relative">
                <textarea name="body" class="feld" rows="3" placeholder="Was du dir dazu merken willst ...">{{ $notiz?->body }}</textarea>
                <span class="absolute right-3 bottom-2 text-xs text-muted" data-status></span>
            </div>
            <div class="eingabe-knoepfe"><button type="submit" class="knopf knopf-leise">Notiz speichern</button></div>
        </form>
    </x-karte>

    <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
        <form method="post" action="{{ route('kurse.erledigt', [$program, $unit]) }}" data-erledigt>
            @csrf
            <input type="hidden" name="an" value="{{ $erledigt ? 0 : 1 }}">
            <button type="submit" @class(['knopf', 'knopf-leise' => ! $erledigt])>{{ $erledigt ? '✓ Erledigt' : 'Als erledigt markieren' }}</button>
        </form>
        @if ($nachher)
            <a href="{{ route('kurse.einheit', [$program, $nachher]) }}" class="knopf">Weiter &rarr;</a>
        @else
            <a href="{{ route('kurse.show', $program) }}" class="knopf">Zurück zur Übersicht</a>
        @endif
    </div>
    @if ($vorher)
        <p class="mt-3"><a href="{{ route('kurse.einheit', [$program, $vorher]) }}" class="hinweis no-underline">&larr; {{ $vorher->title }}</a></p>
    @endif

    @push('scripts')
        <script>window.KURS = { antwort: @json(route('kurse.antwort')) };</script>
    @endpush
</x-layouts.app>

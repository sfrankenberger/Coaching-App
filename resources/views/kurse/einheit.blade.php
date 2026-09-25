<x-layouts.app :title="$unit->title">
    @php $videos = $unit->videoList(); $teile = $unit->answerableExercises(); @endphp
    @php $coach = data_get(app(\App\Tenancy\CurrentTenant::class)->get()?->settings, 'coach_name'); $avatar = app(\App\Tenancy\Branding::class)->get('avatar_url'); @endphp
    <div class="flex items-center gap-3" style="margin:0 0 6px">
        <a href="{{ $unit->step ? route('kurse.schritt', [$program, $unit->step]) : route('kurse.show', $program) }}" class="knopf knopf-ruhig" style="width:44px;padding:0;flex:none" aria-label="Zurück"><i class="fa-solid fa-chevron-left"></i></a>
        <span class="min-w-0">
            <span class="eyebrow block">{{ $unit->step?->title ?? $program->title }}</span>
            <span class="hinweis block truncate">{{ $program->title }}</span>
        </span>
    </div>

    <p class="eyebrow" style="color:var(--c-primary);margin:18px 0 4px">
        {{ \App\Models\Unit::TYPES[$unit->type] ?? 'Schritt' }}@if ($nummer) {{ $nummer }} von {{ $anzahl }}@endif
        @if ($unit->is_core) · Kern @endif
    </p>
    <h1 style="margin:0 0 10px">{{ $unit->title }}</h1>
    <div class="flex flex-wrap items-center gap-2" style="margin:0 0 14px">
        <x-merken art="unit" :id="$unit->id" :an="\App\Models\Bookmark::where('user_id', auth()->id())->where('bookmarkable_type', 'unit')->where('bookmarkable_id', $unit->id)->exists()" :text="true" />
        @foreach ($unit->topics as $t)
            <a href="{{ route('themen.show', $t) }}" class="chip no-underline"><i class="fa-solid fa-tag"></i>{{ $t->name }}</a>
        @endforeach
    </div>
    @if ($unit->intro)
        <div class="karte">
            <p class="eyebrow flex items-center gap-2" style="margin:0 0 8px">@if ($avatar)<img src="{{ $avatar }}" alt="" style="width:22px;height:22px;border-radius:50%;object-fit:cover">@endif Von {{ $coach ?: 'deiner Coachin' }}</p>
            <p class="x whitespace-pre-line" style="margin:0;font-size:var(--fs-base);line-height:1.7">{{ $unit->intro }}</p>
        </div>
    @endif

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
        <x-karte titel="Links" icon="link">
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
                <h2 class="karte-titel" style="margin:0">{{ $unit->type === 'exercise_set' ? 'Deine Antworten' : 'Zum Mitmachen' }}</h2>
                @if ($uebung['total'])
                    <span class="hinweis"><span data-uebung-voll>{{ $uebung['filled'] }}</span> von {{ $uebung['total'] }}</span>
                @endif
            </div>
            @foreach ($unit->exercises as $ex)
                @php $a = $answers->get($ex->id); $v = $a?->value['v'] ?? null; @endphp
                @switch($ex->type)
                    @case('heading')
                        <h3 class="mt-4 mb-2" style="font-size:var(--fs-lg)">{{ $ex->title ?: $ex->prompt }}</h3>
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
                        <button type="submit" @class(['knopf', 'knopf-ruhig' => ! $geteilt])><i class="fa-solid fa-{{ $geteilt ? 'lock-open' : 'lock' }}"></i>{{ $geteilt ? 'Mit deiner Coachin geteilt' : 'Mit deiner Coachin teilen' }}</button>
                    </form>
                    <span class="hinweis">{{ $shareMode === 'alles' ? 'Du teilst grundsätzlich alles. Hier kannst du eine Ausnahme machen.' : 'Nur was du teilst, sieht deine Coachin.' }}</span>
                </div>
            @endif
        </x-karte>
    @endif

    <x-karte titel="Deine Notiz dazu" icon="note-sticky">
        <form method="post" action="{{ route('kurse.notiz', [$program, $unit]) }}" class="eingabe" data-notiz>
            @csrf
            <div class="relative">
                <textarea name="body" class="feld" rows="3" placeholder="Was du dir dazu merken willst ...">{{ $notiz?->body }}</textarea>
                <span class="absolute right-3 bottom-2 text-xs text-muted" data-status></span>
            </div>
            <div class="eingabe-knoepfe"><button type="submit" class="knopf knopf-ruhig">Notiz speichern</button></div>
        </form>
    </x-karte>

    <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
        <form method="post" action="{{ route('kurse.erledigt', [$program, $unit]) }}" data-erledigt>
            @csrf
            <input type="hidden" name="an" value="{{ $erledigt ? 0 : 1 }}">
            <button type="submit" @class(['knopf', 'knopf-ruhig' => ! $erledigt])><i class="fa-solid fa-{{ $erledigt ? 'circle-check' : 'check' }}"></i>{{ $erledigt ? 'Erledigt' : 'Als erledigt markieren' }}</button>
        </form>
        @if ($nachher)
            <a href="{{ route('kurse.einheit', [$program, $nachher]) }}" class="knopf">Weiter <i class="fa-solid fa-arrow-right"></i></a>
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

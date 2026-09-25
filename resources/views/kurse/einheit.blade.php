<x-layouts.app :title="$unit->title">
    @php $videos = $unit->videoList(); $teile = $unit->answerableExercises(); @endphp
    @php $coach = app(\App\Tenancy\Branding::class)->coachName(); $avatar = app(\App\Tenancy\Branding::class)->get('avatar_url'); @endphp
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
                @if ($position)<p class="hinweis" style="margin:4px 6px 8px"><i class="fa-solid fa-clock-rotate-left"></i> Du warst bei {{ gmdate($position >= 3600 ? 'G:i:s' : 'i:s', $position) }}, es geht dort weiter.</p>@endif
                <div class="video" id="video-player" data-medien="unit-{{ $unit->id }}" data-start="{{ (int) $position }}">
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

    @if ($material->isNotEmpty())
        <h2 class="abschnitt"><i class="fa-solid fa-folder-open"></i>Material dazu<em>{{ $material->count() }}</em></h2>
        @foreach ($material as $r)
            @include('kurse._material', ['r' => $r])
        @endforeach
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
                    @case('list')
                        @php $zeilen = array_values(array_filter((array) $v, fn ($z) => is_string($z) && trim($z) !== '')); @endphp
                        <div class="mb-4 wb-liste" data-antwort-liste="{{ $ex->id }}">
                            @if ($ex->prompt)<span class="feld-label feld-label-weich">{{ $ex->prompt }}</span>@endif
                            <ul>
                                @foreach ([...$zeilen, ''] as $z)
                                    <li><input type="text" class="feld" value="{{ $z }}" placeholder="{{ $ex->options['platzhalter'] ?? 'Schreib eine Zeile ...' }}"><button type="button" class="knopf-rund" data-zeile-weg aria-label="Zeile entfernen"><i class="fa-solid fa-xmark"></i></button></li>
                                @endforeach
                            </ul>
                            <div class="flex items-center gap-3"><button type="button" class="knopf knopf-text knopf-klein" data-zeile-mehr><i class="fa-solid fa-plus"></i>{{ $ex->options['mehr'] ?? 'Noch eine' }}</button><span class="hinweis" data-status></span></div>
                        </div>
                        @break
                    @case('pairs')
                        @php $paare = array_values(array_filter((array) $v, fn ($z) => is_array($z))); @endphp
                        <div class="mb-4 wb-liste wb-paare" data-antwort-paare="{{ $ex->id }}">
                            @if ($ex->prompt)<span class="feld-label feld-label-weich">{{ $ex->prompt }}</span>@endif
                            <div class="wb-paare-kopf"><span>{{ $ex->options['links'] ?? 'Der Gedanke' }}</span><span>{{ $ex->options['rechts'] ?? 'Umgedreht' }}</span></div>
                            <ul>
                                @foreach ([...$paare, ['', '']] as $p)
                                    <li><input type="text" class="feld" value="{{ $p[0] ?? '' }}" placeholder="{{ $ex->options['platzhalter_links'] ?? '' }}"><input type="text" class="feld" value="{{ $p[1] ?? '' }}" placeholder="{{ $ex->options['platzhalter_rechts'] ?? '' }}"><button type="button" class="knopf-rund" data-zeile-weg aria-label="Zeile entfernen"><i class="fa-solid fa-xmark"></i></button></li>
                                @endforeach
                            </ul>
                            <div class="flex items-center gap-3"><button type="button" class="knopf knopf-text knopf-klein" data-zeile-mehr><i class="fa-solid fa-plus"></i>{{ $ex->options['mehr'] ?? 'Noch einer' }}</button><span class="hinweis" data-status></span></div>
                        </div>
                        @break
                    @case('letter')
                        <div class="mb-4">
                            @if ($ex->prompt)<label for="ex-{{ $ex->id }}" class="feld-label feld-label-weich">{{ $ex->prompt }}</label>@endif
                            <div class="relative">
                                <textarea id="ex-{{ $ex->id }}" class="feld wb-brief" rows="{{ (int) ($ex->options['zeilen'] ?? 16) }}" placeholder="{{ $ex->options['platzhalter'] ?? 'Ich bin ...' }}" data-antwort="{{ $ex->id }}">{{ is_string($v) ? $v : '' }}</textarea>
                                <span class="absolute right-3 bottom-2 text-xs text-muted" data-status></span>
                            </div>
                        </div>
                        @break
                    @case('mirror')
                        @php $quelle = \App\Models\Exercise::alsText($quellen->get($ex->options['exercise_id'] ?? 0)?->value['v'] ?? null); @endphp
                        <div class="mb-4 wb-spiegel">
                            <span class="eyebrow">{{ $ex->prompt ?: 'Was du gesammelt hast' }}</span>
                            @if ($quelle === '')
                                <p class="hinweis" style="margin:6px 0 0">{{ $ex->options['leer'] ?? 'Hier steht noch nichts.' }}</p>
                            @elseif (str_contains($quelle, "\n"))
                                <ul>@foreach (explode("\n", $quelle) as $z)<li>{{ $z }}</li>@endforeach</ul>
                            @else
                                <p style="margin:6px 0 0">{{ $quelle }}</p>
                            @endif
                        </div>
                        @break
                    @case('audio')
                        @php $vorlage = \App\Models\Exercise::alsText($quellen->get($ex->options['exercise_id'] ?? 0)?->value['v'] ?? null); $hatTon = is_string($v) && $v !== ''; @endphp
                        <div class="mb-4 wb-ton" data-aufnahme-uebung="{{ $ex->id }}" data-ziel="{{ route('uebung.aufnahme') }}">
                            @if ($ex->prompt)<span class="feld-label feld-label-weich">{{ $ex->prompt }}</span>@endif
                            @if ($vorlage !== '')
                                <div class="wb-vorlage"><span class="eyebrow">Dein Text zum Ablesen</span><div class="whitespace-pre-line" style="margin-top:6px">{{ $vorlage }}</div></div>
                            @endif
                            <div class="flex flex-wrap items-center gap-2">
                                <button type="button" class="knopf" data-ton-start><i class="fa-solid fa-microphone"></i>{{ $hatTon ? 'Nochmal aufnehmen' : 'Aufnehmen' }}</button>
                                <button type="button" class="knopf knopf-dunkel" data-ton-stopp hidden><i class="fa-solid fa-stop"></i>Fertig</button>
                                <span class="hinweis" data-ton-zeit></span>
                            </div>
                            <audio controls preload="none" data-ton-spieler @if ($hatTon) src="{{ route('uebung.aufnahme.hoeren', $a) }}" @else hidden @endif style="width:100%;margin-top:10px"></audio>
                        </div>
                        @break
                    @case('takeaway')
                        <div class="mb-4 wb-mitnehmen" data-mitnehmen>
                            <span class="eyebrow">{{ $ex->prompt ?: 'Nimm es mit' }}</span>
                            @if ($mitnehmen->isEmpty())
                                <p class="hinweis" style="margin:6px 0 0">Sobald du etwas aufgeschrieben hast, kannst du es hier mitnehmen.</p>
                            @else
                                <div class="wb-mitnehmen-text" data-mitnehmen-text>@foreach ($mitnehmen as $m)<h4>{{ $m['titel'] }}</h4><p class="whitespace-pre-line">{{ $m['text'] }}</p>@endforeach</div>
                                <div class="flex flex-wrap gap-2" style="margin-top:10px">
                                    <button type="button" class="knopf knopf-ruhig" data-kopieren="{{ $mitnehmen->map(fn ($m) => $m['titel']."\n".$m['text'])->join("\n\n") }}"><i class="fa-regular fa-copy"></i>Text kopieren</button>
                                    <button type="button" class="knopf knopf-ruhig" data-drucken><i class="fa-solid fa-print"></i>Drucken oder als PDF</button>
                                </div>
                            @endif
                        </div>
                        @break
                    @case('practice')
                        @php $start = is_string($v) ? \Illuminate\Support\Carbon::parse($v) : null; $tage = (int) ($ex->options['tage'] ?? 21); $tag = $start ? min($tage, (int) $start->copy()->startOfDay()->diffInDays(now()->startOfDay()) + 1) : 0; @endphp
                        <div class="mb-4 karte flaeche">
                            <span class="eyebrow">{{ $ex->prompt ?: 'Deine tägliche Praxis' }}</span>
                            @if ($start)
                                <p class="karte-titel" style="margin:6px 0">Tag {{ $tag }} von {{ $tage }}</p>
                                <span class="balken" style="display:block"><span style="width: {{ round($tag / $tage * 100) }}%"></span></span>
                                @if ($tag >= 7 && $tag <= 10)<p class="x" style="margin:10px 0 0">Um diese Zeit meldet sich oft der Widerstand. Das ist normal und ein gutes Zeichen. Bleib dran.</p>@endif
                            @else
                                <p class="x" style="margin:6px 0 10px">{{ $tage }} Tage, jeden Tag ein paar Minuten. Du bekommst dafür eine tägliche Aufgabe im Journal.</p>
                                <form method="post" action="{{ route('uebung.praxis', $ex) }}">@csrf<button type="submit" class="knopf"><i class="fa-solid fa-play"></i>Praxis starten</button></form>
                            @endif
                        </div>
                        @break
                    @case('wheel')
                        @php
                            $skalen = $unit->exercises->where('type', 'scale')->values();
                            $n = max(3, $skalen->count());
                            $punkte = $skalen->map(function ($s, $i) use ($answers, $n) {
                                $w = (int) ($answers->get($s->id)?->value['v'] ?? 0);
                                $winkel = -M_PI / 2 + 2 * M_PI * $i / $n;
                                return [round(100 + cos($winkel) * 8 * $w, 1), round(100 + sin($winkel) * 8 * $w, 1), $s, $winkel];
                            });
                        @endphp
                        @if ($skalen->count() >= 3)
                            <figure class="mb-4 wb-rad" data-lebensrad>
                                <svg viewBox="0 0 200 200" role="img" aria-label="Lebensrad">
                                    @foreach ([2, 4, 6, 8, 10] as $r)<circle cx="100" cy="100" r="{{ $r * 8 }}" fill="none" stroke="var(--c-card-border)" stroke-width=".6"/>@endforeach
                                    @foreach ($punkte as [$x, $y, $s, $w])<line x1="100" y1="100" x2="{{ round(100 + cos($w) * 80, 1) }}" y2="{{ round(100 + sin($w) * 80, 1) }}" stroke="var(--c-card-border)" stroke-width=".6"/>@endforeach
                                    <polygon data-rad-flaeche points="{{ $punkte->map(fn ($p) => $p[0].','.$p[1])->join(' ') }}" fill="color-mix(in srgb, var(--c-primary) 25%, transparent)" stroke="var(--c-primary)" stroke-width="1.5"/>
                                </svg>
                                <figcaption class="hinweis" style="text-align:center">{{ $ex->prompt ?: 'Dein Lebensrad' }}: {{ $skalen->map(fn ($s) => rtrim($s->prompt ?: $s->title, ':'))->join(' · ') }}</figcaption>
                            </figure>
                        @endif
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
            {{-- Rueckmeldungen der Coachin zu einzelnen Antworten --}}
            @foreach ($answers->filter(fn ($a) => $a->comments()->exists()) as $a)
                <div id="uebung-{{ $a->exercise_id }}" style="margin-top:14px">
                    <span class="eyebrow block">Rückmeldung zu «{{ \Illuminate\Support\Str::limit($unit->exercises->firstWhere('id', $a->exercise_id)?->prompt ?: $unit->exercises->firstWhere('id', $a->exercise_id)?->title, 60) }}»</span>
                    <x-kommentare :item="$a" />
                </div>
            @endforeach
        </x-karte>
    @endif

    @unless (auth()->user()->canManageCurrentTenant())
        <details class="baustein">
            <summary class="knopf knopf-anstoss" style="cursor:pointer"><i class="fa-solid fa-list-check"></i>Daraus eine Aufgabe machen</summary>
            <form method="post" action="{{ route('aufgaben.store') }}" class="eingabe" style="margin-top:12px">
                @csrf
                <input type="hidden" name="program_id" value="{{ $program->id }}">
                <input type="hidden" name="unit_id" value="{{ $unit->id }}">
                @if ($unit->step_id)<input type="hidden" name="step_id" value="{{ $unit->step_id }}">@endif
                <input type="hidden" name="zurueck" value="{{ url()->current() }}">
                <input name="title" class="feld" maxlength="160" required placeholder="Was nimmst du dir aus dieser Übung vor?">
                <div class="flex flex-wrap items-end gap-2">
                    <label class="block"><span class="feld-label">Bis wann, freiwillig</span><input type="date" name="due_at" class="feld"></label>
                    <button type="submit" class="knopf" style="margin-left:auto"><i class="fa-solid fa-plus"></i>Aufgabe anlegen</button>
                </div>
            </form>
        </details>
    @endunless

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

    <p class="meldung meldung-gut" data-erledigt-hinweis hidden style="margin-top:12px"><i class="fa-solid fa-circle-check"></i> Video fast fertig geschaut, die Einheit ist als erledigt markiert.</p>
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

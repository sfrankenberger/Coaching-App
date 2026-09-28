<x-layouts.app :title="$person->name">
    @php $kt = $lage['kontingent']; @endphp
    <p class="m-0 mb-2"><a href="{{ route('coachees.index') }}" class="hinweis"><i class="fa-solid fa-chevron-left"></i> Coachees</a></p>

    {{-- Kopf: wer, wie erreichbar, Lage --}}
    <section class="karte dossier-kopf">
        <div class="flex items-start gap-3">
            <span class="dossier-avatar">{{ mb_strtoupper(mb_substr($person->name, 0, 1)) }}</span>
            <div class="min-w-0 flex-1">
                <h1 class="m-0" style="font-size:var(--fs-xl)">{{ $person->name }}</h1>
                <p class="hinweis m-0">{{ $person->email }}{{ $person->phone ? ' · '.$person->phone : '' }} · {{ $m->role->label() }}</p>
                <p class="hinweis m-0">Zuletzt hier: {{ $m->last_seen_at ? \App\Support\Zeit::relativ($m->last_seen_at) : 'noch nie' }}{{ $m->joined_at ? ' · dabei seit '.\App\Support\Zeit::datum($m->joined_at) : '' }}</p>
                @if ($lage['stufe'] > 1)<span class="badge {{ $lage['stufe'] === 3 ? 'badge-wartet' : 'badge-rec' }}">{{ $lage['grund'] }}</span>@endif
            </div>
        </div>
        <div class="flex gap-2 mt-3 flex-wrap">
            <a href="mailto:{{ $person->email }}" class="knopf knopf-leise knopf-klein"><i class="fa-solid fa-envelope"></i>Mail</a>
            @if ($whatsapp)<a href="{{ $whatsapp }}" target="_blank" rel="noopener" class="knopf knopf-leise knopf-klein"><i class="fa-brands fa-whatsapp"></i>WhatsApp</a>@endif
            @if ($person->phone)<a href="tel:{{ $person->phone }}" class="knopf knopf-leise knopf-klein"><i class="fa-solid fa-phone"></i>Anrufen</a>@endif
            <form method="post" action="{{ route('coachees.einladung', $m) }}" onsubmit="return confirm('Willkommensmail mit Anmeldelink an {{ $person->email }} schicken?')">@csrf<button type="submit" class="knopf knopf-leise knopf-klein"><i class="fa-solid fa-paper-plane"></i>Einladung</button></form>
            <a href="{{ \App\Filament\Coach\Resources\Memberships\MembershipResource::getUrl('edit', ['record' => $m]) }}" class="knopf knopf-text knopf-klein">Bearbeiten</a>
        </div>
    </section>

    {{-- Pakete und Verkaufen --}}
    <h2 class="abschnitt"><i class="fa-solid fa-box-open"></i>Pakete<em>{{ $pakete->count() }}</em>
        <span class="rechts"><a href="#verkaufen" data-aufklappen="verkaufen">Etwas verkaufen</a></span>
    </h2>
    @forelse ($pakete as $e)
        <div class="zeile">
            <span class="ic"><i class="fa-solid fa-{{ $e->isCurrent() ? 'circle-check' : 'circle-pause' }}"></i></span>
            <span class="tx"><b>{{ $e->offer?->title ?? 'Zugang' }}</b>
                <span>seit {{ \App\Support\Zeit::datum($e->starts_at) }}{{ $e->ends_at ? ' bis '.\App\Support\Zeit::datum($e->ends_at) : '' }} · {{ ['manual' => 'von Hand', 'woocommerce' => 'Shop', 'import' => 'Import'][$e->source] ?? $e->source }}{{ $e->offer?->programs->isNotEmpty() ? ' · '.$e->offer->programs->pluck('title')->join(', ') : '' }}</span>
            </span>
        </div>
    @empty
        <p class="hinweis">Noch kein Paket. {{ $person->vorname() }} sieht nur, was für alle offen ist.</p>
    @endforelse
    @if ($kt)
        <p class="hinweis mt-1 mb-2"><i class="fa-solid fa-ticket"></i> {{ $kt['offen'] }} von {{ $kt['gesamt'] }} Sitzungen offen · {{ $kt['gehabt'] }} gehabt{{ $kt['geplant'] ? ', '.$kt['geplant'].' geplant' : '' }}</p>
    @endif

    <details id="verkaufen" class="karte mt-2" @if ($errors->has('offer_id')) open @endif>
        <summary class="cursor-pointer" style="font-weight:600"><i class="fa-solid fa-cart-plus"></i> Etwas verkaufen oder Zugang geben</summary>
        <form method="post" action="{{ route('coachees.zugang', $m) }}" class="mt-3">
            @csrf
            <label class="feld-label">Angebot
                <select name="offer_id" class="feld" required>
                    <option value="">Bitte wählen</option>
                    @foreach ($angebote as $o)<option value="{{ $o->id }}">{{ $o->title }}{{ $o->access_days ? ' ('.$o->access_days.' Tage)' : '' }}</option>@endforeach
                </select>
            </label>
            <div class="grid gap-2" style="grid-template-columns:1fr 1fr">
                <label class="feld-label">Preis (nur Notiz)<input type="text" name="preis" class="feld" placeholder="z. B. 1200 CHF"></label>
                <label class="feld-label">Laufzeit in Tagen<input type="number" name="tage" class="feld" min="1" placeholder="wie im Angebot"></label>
            </div>
            <label class="feld-label">Inkl. 1:1-Sitzungen (zusätzlich)<input type="number" name="sitzungen" class="feld" min="0" placeholder="0"></label>
            <label class="feld-label">Notiz (nur für dich)<textarea name="notiz" rows="2" class="feld" data-ohne-diktat placeholder="Zahlung, Absprachen, Wünsche"></textarea></label>
            <label class="flex items-center gap-2 mb-3 text-md"><input type="checkbox" name="mail" value="1"> Willkommensmail mit Anmeldelink schicken</label>
            <button type="submit" class="knopf">Freischalten</button>
            @if ($angebote->isEmpty())<p class="hinweis mt-2 mb-0">Noch keine Angebote. <a href="{{ \App\Filament\Coach\Resources\Offers\OfferResource::getUrl('create') }}">Angebot anlegen</a></p>@endif
        </form>
    </details>

    {{-- Kennzahlen --}}
    <div class="kennzahlen mt-3">
        <a href="{{ route('coachees.show', [$m, 'r' => 'termine']) }}" class="kennzahl"><small>Nächster Termin</small><b>{{ $lage['naechster'] ? \App\Support\Zeit::wannKurz($lage['naechster']->starts_at) : 'keiner' }}</b></a>
        <a href="{{ route('coachees.show', [$m, 'r' => 'aufgaben']) }}" class="kennzahl"><small>Wochenaufgaben</small><b>{{ $wochenaufgaben[0] }} von {{ $wochenaufgaben[1] }}</b></a>
        <a href="{{ route('coachees.show', [$m, 'r' => 'termine']) }}" class="kennzahl"><small>Bei den Calls</small><b>{{ $calls[1] ? $calls[0].' von '.$calls[1] : 'keine' }}</b></a>
        <a href="{{ route('coachees.show', [$m, 'r' => 'gespraech']) }}" class="kennzahl"><small>Sie schreibt</small><b>{{ $schreibt }} in 30 Tagen</b></a>
    </div>

    {{-- Reiter --}}
    <div class="segment reiter mt-4 mb-3" role="tablist">
        @foreach (['gespraech' => 'Gespräch', 'termine' => 'Termine', 'kurs' => 'Kurs', 'aufgaben' => 'Aufgaben', 'geteilt' => 'Von ihr freigegeben', 'notizen' => 'Meine Notizen', 'vorbereitung' => 'Vorbereitung'] as $k => $n)
            <a href="{{ route('coachees.show', [$m, 'r' => $k]) }}" @class(['an' => $reiter === $k])>{{ $n }}</a>
        @endforeach
    </div>

    @include('coachees.dossier._'.$reiter)
</x-layouts.app>

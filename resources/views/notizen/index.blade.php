<x-layouts.app title="Meine Notizen">
    @if (App\Support\Funktionen::an('zeitleiste'))<p class="m-0 mb-2"><a href="{{ route('journal.index') }}" class="hinweis no-underline"><i class="fa-solid fa-chevron-left text-[11px]"></i> Mein Journal</a></p>@endif
    <h1>Meine Notizen</h1>

    <div class="baustein">
        <p class="eyebrow m-0 mb-2.5">{{ $bearbeiten ? 'Notiz bearbeiten' : 'Neue Notiz' }}</p>
        <form id="neu" method="post" action="{{ $bearbeiten ? route('notizen.update', $bearbeiten) : route('notizen.store') }}" class="eingabe" enctype="multipart/form-data">
            @csrf
            @if ($aufgabe ?? null)
                <input type="hidden" name="aufgabe_id" value="{{ $aufgabe->id }}">
                <p class="hinweis m-0"><i class="fa-solid fa-list-check"></i> Zur Aufgabe «{{ $aufgabe->title }}». Speichern hakt sie ab.</p>
            @endif
            <input name="title" class="feld" placeholder="Überschrift (optional)" maxlength="160" value="{{ old('title', $bearbeiten?->title) }}">
            <textarea name="body" class="feld" rows="4" placeholder="Was dir gerade durch den Kopf geht ..." required>{{ old('body', $bearbeiten?->body) }}</textarea>
            <x-anhang-wahl :refs="old('refs', $bearbeiten?->anhangRefs() ?? (($aufgabe ?? null) ? ['task:'.$aufgabe->id] : []))" />
            <div class="flex flex-wrap items-center gap-3">
                <label class="knopf knopf-leise knopf-klein cursor-pointer"><i class="fa-solid fa-camera"></i>Foto oder Handschrift<input type="file" name="bild" accept="image/*" hidden onchange="this.parentNode.nextElementSibling.textContent = this.files.length ? this.files[0].name : ''"></label>
                <span class="hinweis"></span>
                @if ($bearbeiten?->image_path)<label class="hinweis flex items-center gap-2"><input type="checkbox" name="bild_weg" value="1" class="accent-primary"> Foto entfernen</label>@endif
                <input type="url" name="link_url" class="feld" style="flex:1;min-width:180px" placeholder="Link dazu (https://...)" value="{{ old('link_url', $bearbeiten?->link_url) }}">
            </div>
            <x-projekt-wahl :projekte="$projekte" :value="old('project_id', $bearbeiten?->project_id)" />
            <div class="flex flex-wrap gap-2">
                @if ($kurse->count())
                    <label class="block"><span class="feld-label">Gehört zu</span><select name="program_id" class="feld"><option value="">Allgemein</option>@foreach ($kurse as $id => $t)<option value="{{ $id }}" @selected((int) old('program_id', $bearbeiten?->program_id) === $id)>{{ $t }}</option>@endforeach</select></label>
                @endif
                <label class="block"><span class="feld-label">Wer sieht das?</span><select name="visibility" class="feld">
                    @foreach (\App\Models\Note::sichtbarkeiten() as $k => $l)
                        @if ($k !== 'program' || $gemeinschaft->count())<option value="{{ $k }}" @selected(old('visibility', $bearbeiten?->visibility ?? 'private') === $k)>{{ $l }}</option>@endif
                    @endforeach
                </select></label>
                <label class="hinweis self-end flex items-center gap-2 pb-2"><input type="checkbox" name="is_pinned" value="1" class="size-5 accent-primary" @checked(old('is_pinned', $bearbeiten?->is_pinned))> Anpinnen</label>
            </div>
            <div class="eingabe-knoepfe">
                @if ($bearbeiten)<a href="{{ route('notizen.index') }}" class="knopf knopf-leise">Abbrechen</a>@endif
                <button type="submit" class="knopf">{{ $bearbeiten ? 'Speichern' : 'Notiz speichern' }}</button>
            </div>
        </form>
    </div>

    <x-filterleiste :filter="$filter" :projekte="$projekte" :kurse="$gemeinschaft->all()" :status="['neu' => 'Mit Kommentaren']" platzhalter="In deinen Notizen suchen" />

    @forelse ($notes as $n)
        <article id="notiz-{{ $n->id }}" @class(['karte', 'heute' => $n->is_pinned])>
            <div class="flex items-start gap-3">
                <div class="min-w-0 flex-1">
                    @if ($n->title)<span class="t">@if ($n->is_pinned)<i class="fa-solid fa-thumbtack" style="color:var(--c-primary);font-size:12px;margin-right:6px"></i>@endif{{ $n->title }}</span>@endif
                    <p class="lesetext whitespace-pre-line m-0 mt-1">{{ $n->body }}</p>
                    @if ($n->image_path || $n->image_url)
                        <a href="{{ $n->image_path ? route('notizen.foto', $n) : $n->image_url }}" target="_blank" rel="noopener"><img src="{{ $n->image_path ? route('notizen.foto', $n) : $n->image_url }}" alt="" class="mt-2 max-h-72 rounded-xl" loading="lazy"></a>
                    @endif
                    @if ($n->link_url)<p class="m-0 mt-1.5"><a href="{{ $n->link_url }}" target="_blank" rel="noopener nofollow" class="text-md"><i class="fa-solid fa-link"></i> {{ \Illuminate\Support\Str::limit(preg_replace('~^https?://(www\.)?~', '', $n->link_url), 60) }}</a></p>@endif
                    <x-anhaenge :item="$n" />
                    <span class="m mt-2">
                        {{ $n->updated_at->translatedFormat('j. M Y, H:i') }}
                        @if ($n->notable) · zu «{{ $n->notable->title ?? '' }}» @endif
                        @if ($n->program) · {{ $n->program->title }} @endif
                        @if ($n->projekt) · <x-projekt-chip :projekt="$n->projekt" /> @endif
                        · {{ \App\Models\Note::sichtbarkeitText($n->visibility) }}
                    </span>
                    <x-kommentare :item="$n" />
                </div>
                <x-element-menue :item="$n" typ="note" :bearbeiten="route('notizen.index', ['bearbeiten' => $n->id])" :loeschen="route('notizen.destroy', $n)" frage="Notiz löschen?" />
            </div>
        </article>
    @empty
        <x-leer icon="note-sticky">Noch keine Notiz. Alles, was du dir merken willst, kommt hierher. Du kannst auch diktieren.</x-leer>
    @endforelse
</x-layouts.app>

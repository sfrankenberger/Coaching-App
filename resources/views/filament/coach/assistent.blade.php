<x-filament-panels::page>
    @php $stand = $this->stand(); @endphp
    <div class="flex flex-wrap gap-2">
        @foreach (['fragen' => 'Fragen', 'themen' => 'Themen prüfen', 'werkzeuge' => 'Werkzeuge', 'geteiltes' => 'Geteiltes'] as $k => $n)
            <x-filament::button :color="$reiter === $k ? 'primary' : 'gray'" size="sm" wire:click="reiterWaehlen('{{ $k }}')">
                {{ $n }}@if ($k === 'themen' && $stand['gesamt'] - $stand['fertig'] > 0) <span class="ms-1 rounded-full bg-white/30 px-1.5 text-xs">{{ $stand['gesamt'] - $stand['fertig'] }}</span>@endif
            </x-filament::button>
        @endforeach
    </div>

    @if ($reiter === 'fragen')
        <x-filament::section heading="Frag mich, was du gerade brauchst" description="Ich kenne deine App und die Fakten zu deinen Menschen: wo du etwas findest, wie du etwas machst, was jemand gebucht hat, wann der letzte Termin war.">
            <form wire:submit="fragen" class="space-y-3">
                <textarea wire:model="frage" rows="2" placeholder="Zum Beispiel: Wo sehe ich, wer auf Antwort wartet? Was hat Nicole gebucht?" class="w-full rounded-lg border-gray-200 text-sm dark:border-white/10 dark:bg-white/5"></textarea>
                @error('frage')<p class="text-sm text-danger-600">{{ $message }}</p>@enderror
                <div class="flex flex-wrap items-center gap-2">
                    <x-filament::button type="submit" icon="heroicon-o-sparkles" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="fragen">Antwort holen</span><span wire:loading wire:target="fragen">Ich schaue nach …</span>
                    </x-filament::button>
                    @unless ($ki)<span class="text-sm text-gray-500">Ohne KI-Schlüssel zeige ich nur die Fakten.</span>@endunless
                </div>
                <div class="flex flex-wrap gap-2">
                    @foreach ($vorschlaege as $v)
                        <button type="button" wire:click="fragen(@js($v))" class="rounded-full border border-gray-200 px-3 py-1 text-xs text-gray-700 hover:border-primary-500 dark:border-white/10 dark:text-gray-300">{{ $v }}</button>
                    @endforeach
                </div>
            </form>
        </x-filament::section>

        @if ($antwort)
            <div class="space-y-4">
                @if ($antwort['text'])
                    <div class="rounded-xl bg-primary-50 p-4 text-sm leading-relaxed whitespace-pre-line dark:bg-primary-500/10">{{ $antwort['text'] }}</div>
                @endif
                @if ($antwort['fehler'])
                    <p class="text-sm text-gray-500">{{ $antwort['fehler'] }}</p>
                @endif
                @if ($antwort['mehrdeutig'])
                    <x-filament::section heading="Wen meinst du?">
                        @foreach ($antwort['mehrdeutig'] as $wort => $namen)
                            <p class="text-sm"><b>{{ $wort }}</b>: {{ implode(', ', $namen) }}</p>
                        @endforeach
                    </x-filament::section>
                @endif
                @foreach ($antwort['menschen'] as $p)
                    <x-filament::section :heading="$p['name']" :description="$p['mail'].($p['telefon'] ? ' · '.$p['telefon'] : '').' · '.$p['rolle']">
                        <dl class="grid gap-2 text-sm sm:grid-cols-2">
                            <div><dt class="text-gray-500">Dabei seit</dt><dd>{{ $p['dabei_seit'] ?: '–' }} · zuletzt da {{ $p['zuletzt_da'] }}</dd></div>
                            <div><dt class="text-gray-500">Lage</dt><dd>{{ $p['lage'] ?: 'alles im Fluss' }}</dd></div>
                            <div><dt class="text-gray-500">Programme</dt><dd>{{ $p['kurse'] ? implode(', ', $p['kurse']) : 'keine' }}</dd></div>
                            <div><dt class="text-gray-500">Zugänge</dt><dd>{{ $p['zugaenge'] ? implode(', ', $p['zugaenge']) : 'keine' }}</dd></div>
                            @if (! empty($p['sitzungen']))<div><dt class="text-gray-500">Sitzungen</dt><dd>{{ $p['sitzungen'] }}</dd></div>@endif
                            <div><dt class="text-gray-500">Nächster 1:1-Termin</dt><dd>{{ $p['naechster_1zu1_termin'] }}</dd></div>
                            <div><dt class="text-gray-500">Letzter 1:1-Termin</dt><dd>{{ $p['letzter_1zu1_termin'] }}</dd></div>
                            @if (! empty($p['naechster_termin_ueberhaupt']))<div><dt class="text-gray-500">Nächster Termin überhaupt</dt><dd>{{ $p['naechster_termin_ueberhaupt'] }}</dd></div>@endif
                            <div><dt class="text-gray-500">Aufgaben</dt><dd>{{ $p['aufgaben'] }}</dd></div>
                            <div><dt class="text-gray-500">Gespräch</dt><dd>{{ $p['gespraech'] }}</dd></div>
                            @if ($p['buchungen'])<div class="sm:col-span-2"><dt class="text-gray-500">Buchungen</dt><dd>{{ implode(' · ', $p['buchungen']) }}</dd></div>@endif
                        </dl>
                        <div class="mt-3 flex gap-4">
                            <x-filament::link :href="$p['dossier']" size="sm" icon="heroicon-o-folder-open">Dossier öffnen</x-filament::link>
                            @if ($p['gespraech_url'])<x-filament::link :href="$p['gespraech_url']" size="sm" icon="heroicon-o-chat-bubble-left-right">Gespräch</x-filament::link>@endif
                        </div>
                    </x-filament::section>
                @endforeach
                @if ($antwort['inhalte'])
                    <x-filament::section heading="Inhalte dazu">
                        <ul class="space-y-1 text-sm">
                            @foreach ($antwort['inhalte'] as $i)
                                <li><a href="{{ $i['url'] }}" target="_blank" rel="noopener" class="font-medium hover:underline">{{ $i['titel'] }}</a> <span class="text-gray-500">{{ $i['art'] }}</span>@if ($i['kurz'])<div class="text-gray-500">{{ $i['kurz'] }}</div>@endif</li>
                            @endforeach
                        </ul>
                    </x-filament::section>
                @endif
                @if ($antwort['orte'])
                    <x-filament::section heading="Wo du es findest">
                        <ul class="space-y-1 text-sm">
                            @foreach ($antwort['orte'] as $o)<li><x-filament::link :href="$o['u']" size="sm">{{ $o['t'] }}</x-filament::link></li>@endforeach
                        </ul>
                    </x-filament::section>
                @endif
            </div>
        @endif
    @elseif ($reiter === 'themen')
        @php $offen = $this->offeneThemen(); @endphp
        <x-filament::section heading="Themen prüfen" :description="$stand['fertig'].' von '.$stand['gesamt'].' Inhalten sind geprüft'.($stand['gesamt'] - $stand['fertig'] > 0 ? ' · '.($stand['gesamt'] - $stand['fertig']).' warten noch' : ' · alles durch').'. Die KI hat Themen, Kurztext und «Hilft, wenn» vorgeschlagen. Passt es, tipp auf Passt. Sonst ändere es am Inhalt.'">
            <div class="mb-4 flex flex-wrap gap-2">
                @foreach ($typen as $k => $n)
                    <x-filament::button :color="$typ === $k ? 'primary' : 'gray'" size="xs" wire:click="typWaehlen('{{ $k }}')">{{ $n }}</x-filament::button>
                @endforeach
            </div>
            @forelse ($offen as $f)
                @php $m = $f->profilable; @endphp
                <div class="border-t border-gray-100 py-3 first:border-0 dark:border-white/5" wire:key="fp-{{ $f->id }}">
                    <div class="text-xs uppercase tracking-wide text-gray-500">{{ \App\Content\Inhalte::ARTEN[$f->profilable_type] ?? $f->profilable_type }}{{ $f->generated_at ? ' · '.$f->generated_at->format('j.n.Y') : '' }}</div>
                    <div class="font-medium">{{ $m?->title ?? '(gelöscht)' }}</div>
                    @if ($f->summary)<p class="mt-1 text-sm text-gray-700 dark:text-gray-300">{{ $f->summary }}</p>@endif
                    @if ($f->helps)<p class="mt-1 text-sm italic text-success-700">{{ $f->helps }}</p>@endif
                    @if ($m)<p class="mt-1 text-xs text-gray-500">{{ $m->topics()->pluck('name')->implode(' · ') ?: 'ohne Thema' }}</p>@endif
                    <div class="mt-2 flex gap-3">
                        <x-filament::button size="xs" color="success" wire:click="passt({{ $f->id }})">Passt</x-filament::button>
                        @if ($url = $this->bearbeitenUrl($m))<x-filament::link :href="$url" size="sm">Ändern</x-filament::link>@endif
                    </div>
                </div>
            @empty
                <p class="text-sm text-gray-500">Hier ist alles geprüft. Schön.</p>
            @endforelse
            <div class="mt-4">{{ $offen->links() }}</div>
        </x-filament::section>
    @elseif ($reiter === 'werkzeuge')
        @php $werkzeuge = $this->werkzeuge(); @endphp
        <x-filament::section heading="Werkzeuge für die Ausbildung" :description="$werkzeuge->count().' Werkzeug'.($werkzeuge->count() === 1 ? '' : 'e').'. Sehen nur Personen mit dem Kennzeichen Coach-Ausbildung und dein Team.'">
            <div class="mb-3 flex gap-3">
                <x-filament::button :href="$toolNeu" tag="a" size="sm" icon="heroicon-o-plus">Neues Werkzeug anlegen</x-filament::button>
                <x-filament::link :href="$toolIndex" size="sm">Alle Werkzeuge</x-filament::link>
            </div>
            @forelse ($werkzeuge as $w)
                <div class="border-t border-gray-100 py-3 first:border-0 dark:border-white/5">
                    <div class="text-xs uppercase tracking-wide text-gray-500">{{ $w->is_published ? 'sichtbar' : 'Entwurf' }}{{ $w->duration ? ' · '.$w->duration : '' }}</div>
                    <div class="font-medium">{{ $w->title }}</div>
                    @if ($w->purpose)<p class="mt-1 text-sm text-gray-700 dark:text-gray-300">{{ \Illuminate\Support\Str::limit($w->purpose, 160) }}</p>@endif
                    <div class="mt-2"><x-filament::link :href="\App\Filament\Coach\Resources\Tools\ToolResource::getUrl('edit', ['record' => $w])" size="sm">Bearbeiten</x-filament::link></div>
                </div>
            @empty
                <p class="text-sm text-gray-500">Noch keins da. Leg das erste an, dann siehst du, wie es sich anfühlt.</p>
            @endforelse
        </x-filament::section>
    @else
        @php $sammlungen = $this->sammlungen(); @endphp
        <x-filament::section heading="Geteiltes" description="Sammlungen, die du im Nachschlagen zusammengestellt und verschickt hast.">
            @forelse ($sammlungen as $s)
                <div class="border-t border-gray-100 py-3 first:border-0 dark:border-white/5">
                    <div class="text-xs uppercase tracking-wide text-gray-500">{{ $s->created_at->format('j.n.Y') }}{{ $s->author ? ' · '.$s->author->vorname() : '' }}</div>
                    <div class="font-medium">{{ $s->title }}</div>
                    <p class="mt-1 text-sm text-gray-700 dark:text-gray-300">{{ $s->empfaenger ?: 'nur als Link' }} · {{ count((array) $s->items) }} Inhalt{{ count((array) $s->items) === 1 ? '' : 'e' }}</p>
                    <div class="mt-2"><x-filament::link :href="$s->url()" size="sm" target="_blank">Ansehen</x-filament::link></div>
                </div>
            @empty
                <p class="text-sm text-gray-500">Du hast noch nichts geteilt. Im Nachschlagen wählst du Inhalte aus und schickst sie jemandem.</p>
            @endforelse
        </x-filament::section>
    @endif
</x-filament-panels::page>

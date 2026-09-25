<x-filament-panels::page>
    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-1">
            <x-filament::section heading="Person">
                <dl class="space-y-2 text-sm">
                    <div><dt class="text-gray-500">E-Mail</dt><dd>{{ $person->email }}</dd></div>
                    <div><dt class="text-gray-500">Telefon</dt><dd>{{ $person->phone ?: '–' }}</dd></div>
                    <div><dt class="text-gray-500">Rolle</dt><dd>{{ $mitgliedschaft->role->label() }} · {{ \App\Models\Membership::statusLabels()[$mitgliedschaft->status] ?? $mitgliedschaft->status }}</dd></div>
                    <div><dt class="text-gray-500">Dabei seit</dt><dd>{{ $mitgliedschaft->joined_at?->format('d.m.Y') ?: '–' }}</dd></div>
                    @if ($lage['stufe'] > 1)
                        <div><dt class="text-gray-500">Lage</dt><dd><x-filament::badge :color="$lage['stufe'] === 3 ? 'danger' : 'warning'" class="inline-flex">{{ $lage['grund'] }}</x-filament::badge></dd></div>
                    @endif
                    @if ($lage['kontingent'])
                        @php $kt = $lage['kontingent']; @endphp
                        <div><dt class="text-gray-500">Sitzungen</dt><dd>{{ $kt['offen'] }} von {{ $kt['gesamt'] }} offen · {{ $kt['gehabt'] }} gehabt{{ $kt['geplant'] ? ', '.$kt['geplant'].' geplant' : '' }}
                            <div class="mt-1 h-1.5 rounded-full bg-gray-100"><div class="h-1.5 rounded-full bg-primary-500" style="width: {{ $kt['gesamt'] ? round(($kt['gehabt'] + $kt['geplant']) / $kt['gesamt'] * 100) : 0 }}%"></div></div></dd></div>
                    @endif
                    @if ($lage['naechster'])
                        <div><dt class="text-gray-500">Nächster Termin</dt><dd>{{ $lage['naechster']->title }} · {{ $lage['naechster']->starts_at->translatedFormat('D j.n. H:i') }}</dd></div>
                    @endif
                    <div><dt class="text-gray-500">Zuletzt da</dt><dd>{{ $mitgliedschaft->last_seen_at?->diffForHumans() ?: '–' }}</dd></div>
                    <div><dt class="text-gray-500">Erreichbar über</dt><dd>{{ $push ? 'Push ('.$push.' Gerät'.($push > 1 ? 'e' : '').')' : 'Mail' }}{{ $telegram ? ', Telegram' : '' }}</dd></div>
                    <div><dt class="text-gray-500">Schalter</dt><dd>
                        Termine {{ $mitgliedschaft->setting('notifications.termine', true) ? 'an' : 'aus' }} ·
                        Abendmail {{ $mitgliedschaft->setting('notifications.abendmail', true) ? 'an' : 'aus' }} ·
                        Aufgaben {{ $mitgliedschaft->setting('notifications.aufgaben', true) ? 'an' : 'aus' }}
                    </dd></div>
                </dl>
            </x-filament::section>

            <x-filament::section heading="Meine Notizen zu {{ $person->vorname() }}" description="Nur für dich und dein Team. {{ $person->vorname() }} sieht das nie.">
                <form wire:submit="notizSpeichern" class="space-y-2">
                    <textarea wire:model="notiz" rows="3" placeholder="Was dir auffällt, was du nicht vergessen willst ..." class="w-full rounded-lg border-gray-200 text-sm dark:border-white/10 dark:bg-white/5"></textarea>
                    <x-filament::button type="submit" size="sm">Notiz speichern</x-filament::button>
                </form>
                @foreach ($coachNotizen as $cn)
                    <div class="mt-3 border-t border-gray-100 pt-2 text-sm dark:border-white/5">
                        <div class="flex items-center gap-2 text-xs text-gray-500">
                            @if ($cn->is_pinned)<x-filament::icon icon="heroicon-s-bookmark" class="h-3.5 w-3.5 text-primary-500" />@endif
                            <span>{{ $cn->created_at->format('d.m.Y H:i') }}{{ $cn->author ? ' · '.$cn->author->vorname() : '' }}</span>
                            <span class="ms-auto flex gap-3">
                                <button type="button" wire:click="notizAnheften({{ $cn->id }})" class="hover:underline">{{ $cn->is_pinned ? 'Lösen' : 'Anheften' }}</button>
                                <button type="button" wire:click="notizLoeschen({{ $cn->id }})" wire:confirm="Notiz löschen?" class="text-danger-600 hover:underline">Löschen</button>
                            </span>
                        </div>
                        <div class="mt-1 whitespace-pre-line">{{ $cn->body }}</div>
                    </div>
                @endforeach
            </x-filament::section>

            <x-filament::section heading="Programme">
                @forelse ($programme as $p)
                    <div class="py-2 border-b border-gray-100 last:border-0">
                        <div class="flex items-center justify-between gap-2 text-sm">
                            <span class="font-medium">{{ $p->title }}</span>
                            <span class="text-gray-500">{{ $p->stand['done'] }} / {{ $p->stand['total'] }}</span>
                        </div>
                        <div class="mt-1 h-1.5 rounded-full bg-gray-100"><div class="h-1.5 rounded-full bg-primary-500" style="width: {{ $p->stand['percent'] }}%"></div></div>
                        <div class="mt-1 text-xs text-gray-500">
                            {{ $p->typeLabel() }}
                            @if ($p->mitglied?->share_mode) · Freigabe: {{ $p->mitglied->share_mode === 'alles' ? 'alles' : 'einzeln' }} @endif
                            @if ($p->mitglied?->last_seen_at) · zuletzt {{ $p->mitglied->last_seen_at->diffForHumans() }} @endif
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-gray-500">Kein Programm.</p>
                @endforelse
            </x-filament::section>

            <x-filament::section heading="Termine">
                @foreach ($einzeltermine as $e)
                    <div class="py-1.5 text-sm border-b border-gray-100 last:border-0">
                        <span class="font-medium">{{ $e->title }}</span>
                        <span class="text-gray-500">· {{ $e->starts_at->format('d.m.Y H:i') }} · 1:1</span>
                    </div>
                @endforeach
                @foreach ($termine as $a)
                    <div class="py-1.5 text-sm border-b border-gray-100 last:border-0">
                        <span class="font-medium">{{ $a->event->title }}</span>
                        <span class="text-gray-500">· {{ $a->event->starts_at->format('d.m.Y') }} ·
                            {{ ['invited' => 'eingeladen', 'declined' => 'abgesagt', 'attended' => 'live dabei', 'watched' => 'Aufzeichnung gesehen'][$a->status] ?? $a->status }}</span>
                    </div>
                @endforeach
                @if ($termine->isEmpty() && $einzeltermine->isEmpty())
                    <p class="text-sm text-gray-500">Noch nichts.</p>
                @endif
            </x-filament::section>
        </div>

        <div class="space-y-6 lg:col-span-2">
            <x-filament::section heading="Geteilte Antworten" description="Nur, was die Person selbst freigegeben hat.">
                @forelse ($antworten as $unitId => $liste)
                    @php $unit = $liste->first()->exercise->unit; @endphp
                    <div class="py-3 border-b border-gray-100 last:border-0">
                        <div class="text-sm font-medium">{{ $unit->title }} <span class="text-gray-500 font-normal">· {{ $unit->program?->title }}</span></div>
                        @foreach ($liste->sortBy(fn ($a) => $a->exercise->position) as $a)
                            <div class="mt-2 text-sm">
                                <div class="text-gray-500">{{ $a->exercise->prompt ?: $a->exercise->title }}</div>
                                <div class="mt-0.5 rounded-lg bg-gray-50 px-3 py-2 whitespace-pre-line">{{ $a->asText() }}</div>
                                @include('filament.coach._kommentare', ['item' => $a, 'typ' => 'answer'])
                            </div>
                        @endforeach
                    </div>
                @empty
                    <p class="text-sm text-gray-500">Sie hat noch keine Übung mit dir geteilt. Was sie schreibt, bleibt privat, bis sie das selbst freigibt.</p>
                @endforelse
            </x-filament::section>

            <x-filament::section heading="Reflexionen" description="Geteilte Wochenreflexionen.">
                @forelse ($reflexionen as $r)
                    <div class="py-3 border-b border-gray-100 last:border-0 text-sm">
                        <div class="text-gray-500">{{ $r->week_label ?: $r->created_at->format('d.m.Y') }} · geteilt {{ $r->shared_at?->format('d.m.Y') }}</div>
                        @foreach (\App\Http\Controllers\ReflexionController::FRAGEN as $k => [$ico, $frage])
                            @if ($r->$k)<div class="mt-2"><b>{{ $ico }} {{ $frage }}</b><div class="whitespace-pre-line">{{ $r->$k }}</div></div>@endif
                        @endforeach
                        @if ($r->addendum)<div class="mt-2"><b>Nachtrag</b><div class="whitespace-pre-line">{{ $r->addendum }}</div></div>@endif
                        @include('filament.coach._kommentare', ['item' => $r, 'typ' => 'reflection'])
                    </div>
                @empty
                    <p class="text-sm text-gray-500">Noch keine geteilte Reflexion.</p>
                @endforelse
            </x-filament::section>

            <x-filament::section heading="Aufgaben">
                @forelse ($aufgaben as $t)
                    <details class="py-1.5 text-sm border-b border-gray-100 last:border-0" @if ($t->comments->isNotEmpty()) open @endif>
                        <summary class="flex items-start gap-2 cursor-pointer list-none">
                            <span class="{{ $t->isDone() ? 'text-success-600' : 'text-gray-300' }}">{{ $t->isDone() ? '✓' : '○' }}</span>
                            <span class="flex-1 {{ $t->isDone() ? 'line-through text-gray-400' : '' }}">{{ $t->title }}@if ($t->comments->isNotEmpty()) <span class="text-xs text-gray-500">· {{ $t->comments->count() }} Kommentar{{ $t->comments->count() === 1 ? '' : 'e' }}</span>@endif</span>
                            <span class="text-gray-500 text-xs">{{ $t->assigned_by ? 'von Coach' : 'selbst' }}{{ $t->due_at ? ' · bis '.$t->due_at->format('d.m.') : '' }}</span>
                        </summary>
                        @if ($t->body)<div class="mt-1 ps-6 text-gray-600 whitespace-pre-line">{{ $t->body }}</div>@endif
                        <div class="ps-6">@include('filament.coach._kommentare', ['item' => $t, 'typ' => 'task'])</div>
                    </details>
                @empty
                    <p class="text-sm text-gray-500">Keine geteilten Aufgaben.</p>
                @endforelse
                @if ($privateAufgaben)
                    <p class="text-xs text-gray-500 mt-2">Dazu {{ $privateAufgaben }} private {{ $privateAufgaben === 1 ? 'Aufgabe' : 'Aufgaben' }}, die nur {{ $person->vorname() }} sieht.</p>
                @endif
            </x-filament::section>

            <x-filament::section heading="Geteilte Notizen">
                @forelse ($notizen as $n)
                    <div class="py-2 border-b border-gray-100 last:border-0 text-sm">
                        @if ($n->title)<div class="font-medium">{{ $n->title }}</div>@endif
                        <div class="whitespace-pre-line">{{ $n->body }}</div>
                        <div class="text-xs text-gray-500 mt-1">{{ $n->updated_at->format('d.m.Y H:i') }}</div>
                        @include('filament.coach._kommentare', ['item' => $n, 'typ' => 'note'])
                    </div>
                @empty
                    <p class="text-sm text-gray-500">Keine geteilten Notizen.</p>
                @endforelse
            </x-filament::section>
        </div>
    </div>
</x-filament-panels::page>

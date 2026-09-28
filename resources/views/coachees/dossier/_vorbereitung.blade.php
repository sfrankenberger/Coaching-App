@if (! $ki)
    <p class="hinweis">Die KI ist für diesen Betrieb nicht eingerichtet (Schlüssel unter Einstellungen).</p>
@else
    <form method="post" action="{{ route('coachees.vorbereitung', $m) }}" class="mb-3" onsubmit="return confirm('Vorbereitung aus Geteiltem, deinen Notizen und dem Gespräch erstellen? Dauert etwa eine Minute.')">
        @csrf<button type="submit" class="knopf knopf-klein"><i class="fa-solid fa-wand-magic-sparkles"></i>{{ $vorbereitung ? 'Neu erstellen' : 'Vorbereitung erstellen' }}</button>
    </form>
@endif
@if ($vorbereitung)
    <div class="karte">
        @if ($vorbereitung->status === 'pending')
            <p class="hinweis m-0"><i class="fa-solid fa-spinner fa-spin"></i> Wird gerade erstellt, du bekommst Bescheid.</p>
        @elseif ($vorbereitung->status === 'failed')
            <p class="hinweis m-0">Das hat nicht geklappt: {{ $vorbereitung->error }}</p>
        @else
            <span class="eyebrow">Erstellt {{ \App\Support\Zeit::relativ($vorbereitung->updated_at) }} · nur aus Geteiltem, deinen Notizen und dem Gespräch</span>
            <div class="prose-app mt-2">
                @foreach (preg_split('/\n{2,}/', (string) $vorbereitung->body) as $block)
                    <p class="whitespace-pre-line">{{ $block }}</p>
                @endforeach
            </div>
        @endif
    </div>
@elseif ($ki)
    <x-leer icon="wand-magic-sparkles">Noch keine Vorbereitung. Ein Tipp auf den Knopf, und du hast vor dem Gespräch alles Wichtige auf einer Seite.</x-leer>
@endif

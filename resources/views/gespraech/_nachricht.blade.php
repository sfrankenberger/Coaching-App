@php
    $ich = auth()->user();
    $meine = $m->user_id === $ich->id;
    $gelesen = $meine && isset($gelesenBis) && $gelesenBis && $gelesenBis->gte($m->created_at);
@endphp
<div id="nachricht-{{ $m->id }}" class="flex {{ $meine ? 'justify-end' : 'justify-start' }}" data-nachricht="{{ $m->id }}" data-tag="{{ $m->created_at->toDateString() }}">
    <div @class(['max-w-[85%] rounded-2xl px-3.5 py-2.5', 'bg-primary text-primary-contrast' => $meine, 'bg-card border border-line' => ! $meine])>
        @if (! $meine && ! $conv->isDirect())
            <span class="block text-xs font-semibold opacity-80 mb-0.5">{{ $m->user?->vorname() ?? 'Jemand' }}</span>
        @endif
        @if (filled($m->body))
            <div class="whitespace-pre-line break-words text-base leading-snug">{{ $m->body }}</div>
        @endif
        @if ($m->hasAudio())
            <audio controls preload="metadata" src="{{ route('nachricht.datei', [$m, 'audio']) }}" class="mt-1 w-56 max-w-full"></audio>
            @if ($m->transcript)
                <details class="mt-1"><summary class="text-xs cursor-pointer opacity-80">Transkript</summary><div class="text-md mt-1 whitespace-pre-line">{{ $m->transcript }}</div></details>
            @endif
        @endif
        @if ($m->hasAttachment())
            @if ($m->attachmentIsImage())
                <a href="{{ route('nachricht.datei', [$m, 'datei']) }}" target="_blank" rel="noopener"><img src="{{ route('nachricht.datei', [$m, 'datei']) }}" alt="" class="mt-1 max-h-64 rounded-xl" loading="lazy"></a>
            @else
                <a href="{{ route('nachricht.datei', [$m, 'datei']) }}" target="_blank" rel="noopener" class="mt-1 block text-md underline {{ $meine ? 'text-primary-contrast' : '' }}">📎 {{ $m->attachment_name ?: 'Datei' }}</a>
            @endif
        @endif
        @if ($m->ref)
            @php $ref = $m->ref; $refLabel = ['task' => 'Aufgabe', 'note' => 'Notiz', 'reflection' => 'Reflexion', 'event' => 'Termin', 'resource' => 'Material', 'unit' => 'Einheit'][$m->ref_type] ?? 'Anhang'; @endphp
            <div class="mt-1 rounded-xl border px-3 py-2 text-md {{ $meine ? 'border-white/40' : 'border-line bg-page' }}">
                <span class="block text-xs uppercase tracking-wider opacity-80">{{ $refLabel }}</span>
                {{ $ref->title ?? ($ref->week_label ?? \Illuminate\Support\Str::limit($ref->body ?? '', 80)) }}
            </div>
        @endif
        <div class="mt-1 flex items-center gap-2 text-xs opacity-80">
            <span>{{ $m->created_at->format('H:i') }}</span>
            @if ($meine)
                <span data-haken data-zeit="{{ $m->created_at->toIso8601String() }}" title="{{ $gelesen ? 'Gelesen' : 'Zugestellt' }}">{{ $gelesen ? '✓✓' : '✓' }}</span>
            @endif
        </div>
        @include('gespraech._reaktionen', ['m' => $m, 'eigene' => $meine])
    </div>
</div>

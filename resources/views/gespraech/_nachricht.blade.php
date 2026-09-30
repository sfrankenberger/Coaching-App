@php
    $ich = auth()->user();
    $chat = app(\App\Chat\Chat::class);
    $alsCoach = $chat->alsCoach($m, $conv);
    $verwaltet = $ich->canManageCurrentTenant();
    // Im 1:1 steht das ganze Team auf einer Seite: was das Team schreibt, ist fuer Lea "meine" Seite
    $meine = $m->user_id === $ich->id || ($alsCoach && $verwaltet);
    $gelesen = $meine && isset($gelesenBis) && $gelesenBis && $gelesenBis->gte($m->created_at);
    $gesicht = $alsCoach ? app(\App\Tenancy\Branding::class)->coach() : $m->user;
@endphp
<div id="nachricht-{{ $m->id }}" class="flex items-end gap-2 {{ $meine ? 'justify-end' : 'justify-start' }}" data-nachricht="{{ $m->id }}" data-tag="{{ $m->created_at->toDateString() }}">
    @if (! $meine)
        <x-avatar :user="$gesicht" :size="30" class="blase-avatar" />
    @endif
    <div @class(['blase', 'blase-meine' => $meine, 'blase-neu' => ! $meine && ($neu ?? false)])>
        @if (! $meine && ! $conv->isDirect())
            <span class="block text-xs font-semibold opacity-80 mb-0.5">{{ $m->user?->vorname() ?? 'Jemand' }}</span>
        @endif
        @if ($alsCoach)
            @if ($verwaltet)
                <span class="block text-xs opacity-70 mb-0.5"><i class="fa-solid fa-pen-nib"></i> geschrieben von {{ $m->user?->vorname() }}</span>
            @else
                <span class="block text-xs font-semibold opacity-80 mb-0.5">{{ $chat->absenderName($m, $conv) }} <span class="chip" style="font-size:10px;padding:1px 7px;vertical-align:middle">{{ $chat->teamName() }}</span></span>
            @endif
        @endif
        @if (filled($m->body))
            <div class="lesetext whitespace-pre-line break-words" style="line-height:1.5">{{ $m->body }}</div>
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
                <a href="{{ route('nachricht.datei', [$m, 'datei']) }}" target="_blank" rel="noopener" class="mt-1 inline-flex items-center gap-2 text-md underline"><i class="fa-solid fa-paperclip"></i>{{ $m->attachment_name ?: 'Datei' }}</a>
            @endif
        @endif
        @if ($m->istVorschlag())
            @php $gebucht = $m->meta['gebucht'] ?? null; $fuerMich = $conv->isDirect() && $conv->user_id === $ich->id; @endphp
            <div class="vorschlaege">
                @foreach ($m->vorschlaege() as $i => $zeit)
                    @if ($gebucht)
                        @if ((int) $gebucht['i'] === $i)<span class="vorschlag an"><i class="fa-solid fa-check"></i>{{ $zeit->translatedFormat('D j. M, H:i') }} gebucht</span>@endif
                    @elseif ($fuerMich && $zeit->isFuture())
                        <form method="post" action="{{ route('nachricht.termin', $m) }}" onsubmit="return confirm('{{ \App\Support\Zeit::wann($zeit) }} buchen?')">
                            @csrf<input type="hidden" name="i" value="{{ $i }}">
                            <button class="vorschlag"><i class="fa-regular fa-calendar"></i>{{ $zeit->translatedFormat('D j. M, H:i') }}</button>
                        </form>
                    @else
                        <span @class(['vorschlag', 'vorbei' => $zeit->isPast()])><i class="fa-regular fa-calendar"></i>{{ $zeit->translatedFormat('D j. M, H:i') }}</span>
                    @endif
                @endforeach
            </div>
        @endif
        @if ($m->ref)
            <div class="anhaenge"><x-anhang-karte :ziel="$m->ref" /></div>
        @endif
        <div class="blase-zeit">
            <span>{{ $m->created_at->format('H:i') }}</span>
            @if ($meine)
                <span data-haken data-zeit="{{ $m->created_at->toIso8601String() }}" title="{{ $gelesen ? 'Gelesen' : 'Zugestellt' }}">{{ $gelesen ? '✓✓' : '✓' }}</span>
            @endif
        </div>
        @include('gespraech._reaktionen', ['m' => $m, 'eigene' => $meine])
    </div>
</div>

<x-layouts.app :title="$folge->title">
    <p style="margin:0 0 8px"><a href="{{ route('impulse.index') }}" class="hinweis no-underline"><i class="fa-solid fa-chevron-left" style="font-size:11px"></i> Impulse</a></p>

    <x-karte>
        <div class="flex items-start gap-4">
            @if ($folge->image_url)
                <img src="{{ $folge->image_url }}" alt="" class="size-20 rounded-lg object-cover shrink-0 bg-page">
            @endif
            <div class="min-w-0">
                <span class="eyebrow">{{ $folge->show }}@if ($folge->episode_number) · Folge {{ $folge->episode_number }}@endif</span>
                <h1 class="mt-1">{{ $folge->title }}</h1>
                <p class="hinweis mt-1">@if ($folge->published_at){{ $folge->published_at->translatedFormat('j. F Y') }}@endif @if ($folge->durationLabel()) · {{ $folge->durationLabel() }}@endif</p>
            </div>
        </div>
        @if ($folge->audio_url)
            <audio controls preload="none" src="{{ $folge->audio_url }}" class="w-full mt-3" id="folge-audio"></audio>
        @endif
        @if ($folge->summary)
            <p class="text-ink-soft mt-3">{{ $folge->summary }}</p>
        @endif
        <div class="mt-4 flex flex-wrap items-center gap-2">
            <x-merken art="episode" :id="$folge->id" :an="$gemerkt->has('episode-'.$folge->id)" :text="true" />
            @if ($folge->url)
                <a href="{{ $folge->url }}" target="_blank" rel="noopener" class="knopf knopf-leise knopf-klein">Zur Folge im Web</a>
            @endif
        </div>
    </x-karte>

    @if ($folge->chapters)
        <x-karte titel="Kapitel" icon="list-ol">
            <ol class="divide-y divide-line">
                @foreach ($folge->chapters as $k)
                    <li>
                        <button type="button" class="flex w-full items-center gap-3 py-2 text-left" data-springe="{{ (int) ($k['start'] ?? 0) }}">
                            <span class="hinweis w-12 shrink-0 tabular-nums">{{ gmdate((int) ($k['start'] ?? 0) >= 3600 ? 'G:i:s' : 'i:s', (int) ($k['start'] ?? 0)) }}</span>
                            <span class="text-md">{{ $k['titel'] ?? $k['title'] ?? '' }}</span>
                        </button>
                    </li>
                @endforeach
            </ol>
        </x-karte>
    @endif

    @if ($folge->body)
        <x-karte titel="Shownotes"><div class="prose-app">{!! $folge->body !!}</div></x-karte>
    @endif

    @if ($folge->faq)
        <x-karte titel="Fragen dazu">
            @foreach ($folge->faq as $f)
                <details class="py-2 border-b border-line last:border-0">
                    <summary class="cursor-pointer text-base">{{ $f['frage'] ?? '' }}</summary>
                    <p class="text-md text-ink-soft mt-1">{{ $f['antwort'] ?? '' }}</p>
                </details>
            @endforeach
        </x-karte>
    @endif

    @if ($folge->transcript)
        <x-karte>
            <details>
                <summary class="cursor-pointer text-base font-semibold">Abschrift</summary>
                <div class="prose-app mt-2 abschrift">{!! str_contains($folge->transcript, '<p') ? $folge->transcript : nl2br(e($folge->transcript)) !!}</div>
            </details>
        </x-karte>
    @endif

    @if ($folge->topics->isNotEmpty() || $folge->keywords)
        <x-karte titel="Themen" icon="tag">
            <div class="flex flex-wrap gap-2">
                @foreach ($folge->topics as $t)
                    <a href="{{ route('themen.show', $t) }}" class="chip no-underline"><i class="fa-solid fa-tag"></i>{{ $t->name }}</a>
                @endforeach
            </div>
            @if ($folge->keywords)
                <p class="hinweis mt-2">{{ implode(' · ', $folge->keywords) }}</p>
            @endif
        </x-karte>
    @endif

    @push('scripts')
    <script>
        (function () {
            var audio = document.getElementById('folge-audio');
            if (!audio) return;
            document.querySelectorAll('[data-springe]').forEach(function (b) {
                b.addEventListener('click', function () { audio.currentTime = parseInt(b.dataset.springe, 10) || 0; audio.play(); window.scrollTo({ top: 0, behavior: 'smooth' }); });
            });
            document.querySelectorAll('.abschrift [data-start]').forEach(function (p) {
                p.style.cursor = 'pointer';
                p.addEventListener('click', function () { audio.currentTime = parseInt(p.dataset.start, 10) || 0; audio.play(); });
            });
        })();
    </script>
    @endpush
</x-layouts.app>

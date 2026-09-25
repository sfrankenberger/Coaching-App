<x-layouts.app :title="$thema->name">
    <p class="m-0 mb-2"><a href="{{ route('themen.index') }}" class="hinweis no-underline"><i class="fa-solid fa-chevron-left text-[11px]"></i> Themen</a></p>
    <h1 class="mb-1">{{ $thema->name }}</h1>
    @if ($thema->description)<p class="unterzeile m-0 mb-3.5">{{ $thema->description }}</p>@endif

    @forelse ($gruppen as $art => $zeilen)
        <h2 class="abschnitt">{{ \App\Content\Inhalte::ARTEN[$art] ?? $art }}{{ $zeilen->count() > 1 ? 'e' : '' }}<em>{{ $zeilen->count() }}</em></h2>
        @foreach ($zeilen as $z)
            @php $f = $z['model']->finder ?? null; @endphp
            <article class="karte flex items-start gap-3">
                @if ($z['bild'])
                    <a href="{{ $z['url'] }}" class="shrink-0"><img src="{{ $z['bild'] }}" alt="" class="size-16 rounded-lg object-cover bg-page" loading="lazy"></a>
                @endif
                <div class="min-w-0 flex-1">
                    <a href="{{ $z['url'] }}" class="block text-base leading-snug no-underline text-ink">{{ $z['titel'] }}</a>
                    <span class="hinweis block">{{ $z['typ'] }}@if ($z['ts']) · {{ $z['ts']->translatedFormat('j. M Y') }}@endif</span>
                    @if ($f?->summary)
                        <p class="text-md text-ink-soft mt-1">{{ $f->summary }}</p>
                        @if ($f->helps)<p class="hinweis mt-1"><em>{{ $f->helps }}</em></p>@endif
                    @elseif ($z['text'])
                        <p class="text-md text-ink-soft mt-1">{{ \Illuminate\Support\Str::limit(strip_tags($z['text']), 160) }}</p>
                    @endif
                </div>
                <x-merken :art="$z['art']" :id="$z['id']" :an="$gemerkt->has($z['art'].'-'.$z['id'])" />
            </article>
        @endforeach
    @empty
        <div class="leer"><i class="fa-regular fa-bookmark"></i>Zu diesem Thema ist für dich gerade nichts freigeschaltet.</div>
    @endforelse
</x-layouts.app>

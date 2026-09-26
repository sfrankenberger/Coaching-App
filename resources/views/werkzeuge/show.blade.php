<x-layouts.app :title="$werkzeug->title">
    <p class="m-0 mb-2"><a href="{{ route('werkzeuge.index') }}" class="hinweis no-underline"><i class="fa-solid fa-chevron-left text-[11px]"></i> Werkzeuge</a></p>
    <div class="flex items-start justify-between gap-3">
        <div>
            <span class="eyebrow"><i class="fa-solid fa-hammer"></i> Werkzeug @if ($werkzeug->duration)· {{ $werkzeug->duration }}@endif</span>
            <h1 class="mb-1 mt-1">{{ $werkzeug->title }}</h1>
        </div>
        <x-merken art="tool" :id="$werkzeug->id" :an="$gemerkt->has('tool-'.$werkzeug->id)" />
    </div>
    @if ($werkzeug->topics->isNotEmpty())
        <p class="m-0 mb-3 flex flex-wrap gap-1.5">
            @foreach ($werkzeug->topics as $t)<a href="{{ route('themen.show', $t) }}" class="chip no-underline">{{ $t->name }}</a>@endforeach
        </p>
    @endif

    @foreach ($felder as $key => [$titel, $hilfe])
        @continue($key === 'duration')
        <section class="karte">
            <span class="eyebrow">{{ $titel }}</span>
            <div class="lesetext mt-1">{!! nl2br(e($werkzeug->{$key})) !!}</div>
        </section>
    @endforeach
</x-layouts.app>

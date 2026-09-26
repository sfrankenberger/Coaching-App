<x-layouts.app :title="$sammlung->title">
    <p class="eyebrow mb-1">Von {{ $sammlung->author?->vorname() ?? app(\App\Tenancy\Branding::class)->coachName() }} für dich</p>
    <h1 class="mb-2">{{ $sammlung->title }}</h1>
    @if ($sammlung->greeting)<p class="lesetext mb-4">{!! nl2br(e($sammlung->greeting)) !!}</p>@endif
    <div class="fu-liste">
        @forelse ($karten as $k)
            @include('nachschlagen._karte')
        @empty
            <x-leer icon="folder-open">Diese Sammlung ist leer.</x-leer>
        @endforelse
    </div>
    <p class="mt-4"><a href="{{ route('nachschlagen.index') }}" class="knopf knopf-leise knopf-klein"><i class="fa-solid fa-magnifying-glass"></i>Selber nachschlagen</a></p>
</x-layouts.app>

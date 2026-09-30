<x-layouts.app :title="'Fragen · '.$program->title">
    <div style="--kc: {{ $program->color ?: '#7C8C9A' }}">
        <p class="m-0 mb-2"><a href="{{ route('kurse.show', $program) }}" class="hinweis no-underline"><i class="fa-solid fa-chevron-left text-[11px]"></i> {{ $program->title }}</a></p>
        <h1 class="mb-1">Fragen an {{ $coach }}</h1>
        <p class="unterzeile m-0 mb-3.5">Was dich beschäftigt, hilft oft auch den anderen. {{ $coach }} beantwortet die Fragen hier oder nimmt sie in den nächsten Call.</p>

        @include('fragen._formular', ['action' => route('kurse.fragen.store', $program), 'program' => $program])

        @include('fragen._liste', ['basis' => ['url' => route('kurse.fragen', $program)]])
    </div>
</x-layouts.app>

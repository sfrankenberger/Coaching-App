{{-- "Gehoert zu": Projekt waehlen im Formular. Zeigt nichts, wenn die Person keine Projekte hat. --}}
@props(['projekte', 'value' => null, 'name' => 'project_id'])
@if ($projekte->isNotEmpty())
    <div>
        <span class="feld-label">Gehört zu</span>
        <div class="pillen m-0">
            <label class="pille" style="cursor:pointer"><input type="radio" name="{{ $name }}" value="" class="sr-only" @checked(! $value)>ohne Projekt</label>
            @foreach ($projekte as $p)
                <label class="pille" style="cursor:pointer;--kc: {{ $p->farbe }}"><input type="radio" name="{{ $name }}" value="{{ $p->id }}" class="sr-only" @checked((int) $value === $p->id)><span class="punkt"></span>{{ $p->name }}</label>
            @endforeach
        </div>
    </div>
@endif

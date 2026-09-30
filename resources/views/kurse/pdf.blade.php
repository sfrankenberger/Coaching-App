<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<title>{{ $program->title }}</title>
<style>
    @page { margin: 22mm 18mm 20mm 18mm; }
    body { font-family: "DejaVu Sans", sans-serif; font-size: 10.5pt; color: #222; line-height: 1.45; }
    h1 { font-family: "DejaVu Serif", serif; font-weight: normal; font-size: 24pt; margin: 0 0 4pt; color: {{ $farbe }}; }
    h2 { font-family: "DejaVu Serif", serif; font-weight: normal; font-size: 16pt; margin: 0 0 10pt; padding-bottom: 4pt; border-bottom: 1.5pt solid {{ $farbe }}; }
    h3 { font-size: 11.5pt; margin: 14pt 0 4pt; color: {{ $farbe }}; }
    .meta { color: #666; font-size: 9pt; margin: 0 0 24pt; }
    .eyebrow { font-size: 8pt; letter-spacing: .08em; text-transform: uppercase; color: #888; margin: 0 0 2pt; }
    .einheit { page-break-before: always; }
    .einheit.erste { page-break-before: auto; }
    .teil { margin: 0 0 12pt; page-break-inside: avoid; }
    .frage { font-weight: bold; margin: 0 0 3pt; }
    .antwort { white-space: pre-wrap; margin: 0; padding: 6pt 8pt; background: #f5f4f1; border-left: 2pt solid {{ $farbe }}; }
    .leer { color: #999; font-style: italic; padding: 4pt 0; }
    .hinweis { color: #555; font-style: italic; margin: 0 0 10pt; }
    .skala { margin: 2pt 0; }
    .skala span { display: inline-block; width: 16pt; height: 16pt; line-height: 16pt; text-align: center; border: 1pt solid #ccc; border-radius: 8pt; font-size: 8.5pt; margin-right: 2pt; color: #666; }
    .skala span.an { background: {{ $farbe }}; border-color: {{ $farbe }}; color: #fff; }
    .chip { display: inline-block; border: 1pt solid #ccc; border-radius: 10pt; padding: 1pt 8pt; margin: 0 4pt 4pt 0; font-size: 9.5pt; color: #666; }
    .chip.an { background: {{ $farbe }}; border-color: {{ $farbe }}; color: #fff; }
    ul { margin: 0; padding-left: 16pt; } li { margin: 0 0 2pt; }
    table { border-collapse: collapse; width: 100%; } th, td { text-align: left; vertical-align: top; padding: 4pt 6pt; border-bottom: 1pt solid #e2e0da; } th { font-size: 8.5pt; color: #777; text-transform: uppercase; letter-spacing: .05em; }
    .notiz { margin-top: 14pt; padding: 8pt 10pt; border: 1pt dashed #bbb; }
    .fuss { position: fixed; bottom: -10mm; left: 0; right: 0; font-size: 8pt; color: #999; text-align: center; }
</style>
</head>
<body>
<div class="fuss">{{ $program->title }} · {{ $user->name }} · {{ $app }}</div>

<p class="eyebrow">{{ $program->typeLabel() }}</p>
<h1>{{ $program->title }}</h1>
@if ($program->subtitle)<p style="margin:0 0 6pt;color:#555">{{ $program->subtitle }}</p>@endif
<p class="meta">{{ $user->name }} · Stand {{ $datum }} · begleitet von {{ $coach }}</p>

@forelse ($einheiten as $i => $e)
    <div class="einheit {{ $i === 0 ? 'erste' : '' }}">
        @if ($e['schritt'])<p class="eyebrow">{{ $e['schritt'] }}</p>@endif
        <h2>{{ $e['unit']->title }}</h2>
        @foreach ($e['teile'] as $t)
            @switch($t['art'])
                @case('heading')
                    <h3>{{ $t['text'] }}</h3>
                    @break
                @case('hint')
                    <p class="hinweis">{{ $t['text'] }}</p>
                    @break
                @case('scale')
                    <div class="teil"><p class="frage">{{ $t['frage'] }}</p>
                        <div class="skala">@for ($n = 1; $n <= 10; $n++)<span class="{{ $t['wert'] === $n ? 'an' : '' }}">{{ $n }}</span>@endfor</div>
                    </div>
                    @break
                @case('values')
                @case('choice')
                    <div class="teil"><p class="frage">{{ $t['frage'] }}</p>
                        <div>@foreach ($t['optionen'] as $o)<span class="chip {{ in_array($o, $t['gewaehlt'], true) ? 'an' : '' }}">{{ $o }}</span>@endforeach</div>
                    </div>
                    @break
                @case('checkbox')
                    <div class="teil"><p style="margin:0">{{ $t['haken'] ? '☑' : '☐' }} {{ $t['frage'] }}</p></div>
                    @break
                @case('list')
                    <div class="teil">@if ($t['frage'])<p class="frage">{{ $t['frage'] }}</p>@endif
                        @if ($t['zeilen'])<ul>@foreach ($t['zeilen'] as $z)<li>{{ $z }}</li>@endforeach</ul>@else<p class="leer">Noch nichts eingetragen.</p>@endif
                    </div>
                    @break
                @case('pairs')
                    <div class="teil">@if ($t['frage'])<p class="frage">{{ $t['frage'] }}</p>@endif
                        @if ($t['paare'])
                            <table><tr><th style="width:50%">{{ $t['links'] }}</th><th>{{ $t['rechts'] }}</th></tr>
                            @foreach ($t['paare'] as $p)<tr><td>{{ $p[0] ?? '' }}</td><td>{{ $p[1] ?? '' }}</td></tr>@endforeach</table>
                        @else<p class="leer">Noch nichts eingetragen.</p>@endif
                    </div>
                    @break
                @case('wheel')
                    <div class="teil"><p class="frage">{{ $t['frage'] ?: 'Dein Lebensrad' }}</p>
                        <table>@foreach ($t['skalen'] as $s)<tr><td style="width:55%">{{ $s['name'] }}</td><td>{{ $s['wert'] ?: '–' }} von 10</td></tr>@endforeach</table>
                    </div>
                    @break
                @default
                    <div class="teil">@if ($t['frage'])<p class="frage">{{ $t['frage'] }}</p>@endif
                        @if (trim($t['text'] ?? '') !== '')<p class="antwort">{{ $t['text'] }}</p>@else<p class="leer">Noch nichts eingetragen.</p>@endif
                    </div>
            @endswitch
        @endforeach
        @if (filled($e['notiz']))
            <div class="notiz"><p class="eyebrow">Meine Notiz</p><p class="antwort" style="background:none;border:0;padding:0">{{ $e['notiz'] }}</p></div>
        @endif
    </div>
@empty
    <p class="leer">In diesem Kurs gibt es noch nichts zum Ausfüllen.</p>
@endforelse
</body>
</html>

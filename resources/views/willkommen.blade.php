<x-layouts.app title="Willkommen">
    <div data-willkommen>
        <div class="h-1.5 rounded-full bg-line overflow-hidden mb-3"><span class="block h-full rounded-full bg-primary transition-all" data-balken style="width: {{ round(100 / count($schritte)) }}%"></span></div>

        <form method="post" action="{{ route('willkommen.fertig') }}" id="willkommen-form">
            @csrf
            @foreach ($schritte as $i => $s)
                <section class="karte" data-schritt @if ($i > 0) hidden @endif>
                    <span class="hinweis uppercase tracking-wider text-xs font-semibold">Schritt {{ $i + 1 }} von {{ count($schritte) }}</span>
                    <h1 class="mt-1">{{ $s['titel'] }}</h1>
                    <div class="prose-app mt-2">{!! $s['text'] !!}</div>
                    @if (! empty($s['push']) && $pushMoeglich)
                        <div class="mt-3" data-push data-schluessel="{{ route('push.schluessel') }}" data-abo="{{ route('push.abo') }}">
                            <button type="button" class="knopf" data-push-an>Ja, Push einschalten</button>
                            <button type="button" class="knopf knopf-leise" data-push-aus hidden>Ausschalten</button>
                            <span class="hinweis ml-2" data-push-status></span>
                        </div>
                    @endif
                    @if (! empty($s['telefon']))
                        <label class="feld-label mt-3 block">Deine Handynummer (freiwillig)
                            <input type="tel" name="phone" value="{{ $telefon }}" class="feld mt-1" placeholder="079 123 45 67" autocomplete="tel">
                        </label>
                    @endif
                </section>
            @endforeach

            <div class="flex items-center justify-between gap-2 mt-3">
                <button type="button" class="knopf knopf-leise" data-zurueck hidden>Zurück</button>
                <span class="flex-1"></span>
                @if ($gesehen)
                    <a href="{{ route('home') }}" class="knopf knopf-leise">Schliessen</a>
                @endif
                <button type="button" class="knopf" data-weiter>Weiter</button>
                <button type="submit" class="knopf" data-los hidden>Los geht es</button>
            </div>
        </form>
    </div>

    @push('scripts')
    <script>
        (function () {
            var wrap = document.querySelector('[data-willkommen]'); if (!wrap) return;
            var s = wrap.querySelectorAll('[data-schritt]'), balken = wrap.querySelector('[data-balken]');
            var zurueck = wrap.querySelector('[data-zurueck]'), weiter = wrap.querySelector('[data-weiter]'), los = wrap.querySelector('[data-los]');
            var i = 0;
            function zeig() {
                s.forEach(function (el, k) { el.hidden = k !== i; });
                balken.style.width = Math.round((i + 1) / s.length * 100) + '%';
                zurueck.hidden = i === 0;
                weiter.hidden = i === s.length - 1;
                los.hidden = i !== s.length - 1;
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }
            weiter.addEventListener('click', function () { if (i < s.length - 1) { i++; zeig(); } });
            zurueck.addEventListener('click', function () { if (i > 0) { i--; zeig(); } });
            document.getElementById('willkommen-form').addEventListener('keydown', function (e) { if (e.key === 'Enter' && e.target.tagName !== 'TEXTAREA') { e.preventDefault(); if (i < s.length - 1) { i++; zeig(); } } });
            zeig();
        })();
    </script>
    @endpush
</x-layouts.app>

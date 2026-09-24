/* Kleine Helfer fuer die App: automatisches Speichern von Antworten, Umschalten, Videoliste.
   Ohne Build, laeuft direkt im Browser. */
(function () {
    var csrf = document.querySelector('meta[name="csrf-token"]');
    csrf = csrf ? csrf.getAttribute('content') : '';

    function post(url, data) {
        return fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
            body: JSON.stringify(data || {})
        }).then(function (r) { if (!r.ok) throw new Error(r.status); return r.json(); });
    }

    function status(el, text) {
        var wrap = el.closest('.relative') || el.parentNode;
        var s = wrap ? wrap.querySelector('[data-status]') : null;
        if (s) s.textContent = text;
    }

    function zaehlen() {
        var box = document.querySelector('[data-uebung]');
        if (!box) return;
        var voll = 0;
        box.querySelectorAll('[data-antwort]').forEach(function (t) { if (t.value.trim() !== '') voll++; });
        box.querySelectorAll('[data-antwort-skala]').forEach(function (s) { if (s.querySelector('[data-wert].bg-primary')) voll++; });
        box.querySelectorAll('[data-antwort-werte]').forEach(function (w) { if (w.querySelector('[data-wert].bg-primary')) voll++; });
        box.querySelectorAll('[data-antwort-haken]').forEach(function (h) { if (h.checked) voll++; });
        var z = box.querySelector('[data-uebung-voll]');
        if (z) z.textContent = voll;
    }

    /* Antworten: Textfelder verzoegert beim Tippen, sofort beim Verlassen */
    var timer = {};
    function antwortSenden(id, wert, el) {
        if (!window.KURS) return;
        if (el) status(el, 'speichert ...');
        post(window.KURS.antwort, { exercise_id: id, value: wert })
            .then(function () { if (el) status(el, 'gespeichert'); zaehlen(); })
            .catch(function () { if (el) status(el, 'nicht gespeichert, bitte nochmals'); });
    }
    document.addEventListener('input', function (e) {
        var el = e.target;
        if (!el.dataset || !el.dataset.antwort) return;
        clearTimeout(timer[el.dataset.antwort]);
        timer[el.dataset.antwort] = setTimeout(function () { antwortSenden(el.dataset.antwort, el.value, el); }, 1200);
    });
    document.addEventListener('focusout', function (e) {
        var el = e.target;
        if (!el.dataset || !el.dataset.antwort) return;
        clearTimeout(timer[el.dataset.antwort]);
        antwortSenden(el.dataset.antwort, el.value, el);
    });
    document.addEventListener('change', function (e) {
        var el = e.target;
        if (el.dataset && el.dataset.antwortHaken) {
            antwortSenden(el.dataset.antwortHaken, el.checked ? 1 : 0, null);
            var t = el.parentNode.querySelector('span');
            if (t) { t.classList.toggle('text-muted', el.checked); t.classList.toggle('line-through', el.checked); }
        }
    });

    function markieren(btn, an) {
        btn.classList.toggle('bg-primary', an);
        btn.classList.toggle('text-primary-contrast', an);
        btn.classList.toggle('border-primary', an);
        btn.classList.toggle('border-line', !an);
        btn.classList.toggle('bg-page', !an);
    }

    document.addEventListener('click', function (e) {
        var b = e.target.closest('button');
        if (!b) return;

        var skala = b.closest('[data-antwort-skala]');
        if (skala && b.dataset.wert) {
            var war = b.classList.contains('bg-primary');
            skala.querySelectorAll('[data-wert]').forEach(function (x) { markieren(x, false); });
            if (!war) markieren(b, true);
            antwortSenden(skala.dataset.antwortSkala, war ? '' : b.dataset.wert, null);
            return;
        }

        var werte = b.closest('[data-antwort-werte]');
        if (werte && b.dataset.wert) {
            var einzeln = werte.dataset.einzeln === '1';
            var an = !b.classList.contains('bg-primary');
            if (einzeln) werte.querySelectorAll('[data-wert]').forEach(function (x) { markieren(x, false); });
            markieren(b, an);
            var g = [];
            werte.querySelectorAll('[data-wert].bg-primary').forEach(function (x) { g.push(x.dataset.wert); });
            var z = werte.querySelector('[data-zahl]');
            if (z) z.textContent = g.length;
            antwortSenden(werte.dataset.antwortWerte, g, null);
            return;
        }

        var wahl = b.closest('.video-wahl');
        if (wahl) {
            var player = document.getElementById('video-player');
            if (!player || !wahl.dataset.src) return;
            var alt = player.querySelector('iframe, video');
            if (alt && alt.tagName.toLowerCase() === wahl.dataset.kind.replace('file', 'video')) {
                alt.src = wahl.dataset.src;
            } else {
                player.innerHTML = wahl.dataset.kind === 'file'
                    ? '<video controls preload="metadata" src="' + wahl.dataset.src + '"></video>'
                    : '<iframe src="' + wahl.dataset.src + '" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen title="Video"></iframe>';
            }
            document.querySelectorAll('.video-wahl').forEach(function (x) { x.classList.remove('text-primary', 'font-semibold'); });
            wahl.classList.add('text-primary', 'font-semibold');
            player.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    });

    /* Notiz: verzoegert speichern, Formular bleibt als Fallback */
    var notizTimer;
    document.addEventListener('input', function (e) {
        var f = e.target.closest('form[data-notiz]');
        if (!f) return;
        clearTimeout(notizTimer);
        notizTimer = setTimeout(function () {
            status(e.target, 'speichert ...');
            post(f.action, { body: f.body.value }).then(function (j) { status(e.target, 'gespeichert ' + (j.zeit || '')); }).catch(function () { status(e.target, 'nicht gespeichert'); });
        }, 1500);
    });
})();

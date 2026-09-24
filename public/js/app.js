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

/* ---------- Gespraech: Senden ohne Neuladen, Nachfragen alle 5 Sekunden, Sprachnachricht ---------- */
(function () {
    var verlauf = document.getElementById('verlauf');
    var form = document.querySelector('form[data-senden]');
    if (!verlauf || !form) return;
    var csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    var ich = verlauf.dataset.ich;

    function nachUnten() { window.scrollTo({ top: document.body.scrollHeight, behavior: 'smooth' }); }
    function tagTrenner() {
        var letzter = '';
        verlauf.querySelectorAll('.tag-trenner').forEach(function (t) { t.remove(); });
        verlauf.querySelectorAll('[data-nachricht]').forEach(function (n) {
            if (n.dataset.tag !== letzter) {
                letzter = n.dataset.tag;
                var d = document.createElement('div');
                d.className = 'tag-trenner hinweis text-center my-1';
                var p = letzter.split('-');
                d.textContent = p[2] + '.' + p[1] + '.' + p[0];
                n.parentNode.insertBefore(d, n);
            }
        });
    }
    function haken(bisIso) {
        if (!bisIso) return;
        var bis = new Date(bisIso).getTime();
        verlauf.querySelectorAll('[data-haken]').forEach(function (h) {
            if (new Date(h.dataset.zeit).getTime() <= bis) { h.textContent = '✓✓'; h.title = 'Gelesen'; }
        });
    }
    function anhaengen(html) {
        if (!html) return;
        var leer = verlauf.querySelector('[data-leer]');
        if (leer) leer.remove();
        var box = document.createElement('div');
        box.innerHTML = html;
        while (box.firstChild) verlauf.appendChild(box.firstChild);
        tagTrenner();
        nachUnten();
    }
    tagTrenner();
    nachUnten();

    /* Nachfragen */
    var laeuft = false;
    function nachfragen() {
        if (laeuft || document.hidden) return;
        laeuft = true;
        fetch(verlauf.dataset.verlauf + '?seit=' + verlauf.dataset.letzte, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (j) {
                if (j.letzte && j.letzte != verlauf.dataset.letzte) { verlauf.dataset.letzte = j.letzte; anhaengen(j.html); }
                haken(j.gelesen_bis);
            })
            .catch(function () {})
            .finally(function () { laeuft = false; });
    }
    setInterval(nachfragen, 5000);
    document.addEventListener('visibilitychange', function () { if (!document.hidden) nachfragen(); });

    /* Senden */
    var textarea = form.querySelector('textarea[name="body"]');
    var dateiInput = form.querySelector('input[name="file"]');
    var dateiName = form.querySelector('[data-datei-name]');
    textarea.addEventListener('input', function () { textarea.style.height = 'auto'; textarea.style.height = Math.min(160, textarea.scrollHeight) + 'px'; });
    textarea.addEventListener('keydown', function (e) { if (e.key === 'Enter' && (e.metaKey || e.ctrlKey)) { e.preventDefault(); form.requestSubmit(); } });
    dateiInput.addEventListener('change', function () {
        if (dateiInput.files.length) { dateiName.hidden = false; dateiName.textContent = '📎 ' + dateiInput.files[0].name; } else { dateiName.hidden = true; }
    });
    function senden(fd) {
        return fetch(form.action, { method: 'POST', credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf }, body: fd })
            .then(function (r) { return r.json().then(function (j) { if (!r.ok) throw new Error(j.fehler || 'Senden fehlgeschlagen'); return j; }); })
            .then(function (j) { verlauf.dataset.letzte = j.id; anhaengen(j.html); });
    }
    form.addEventListener('submit', function (e) {
        e.preventDefault();
        var fd = new FormData(form);
        if (!textarea.value.trim() && !dateiInput.files.length) return;
        senden(fd).then(function () {
            textarea.value = ''; textarea.style.height = 'auto'; dateiInput.value = ''; dateiName.hidden = true;
        }).catch(function (err) { alert(err.message); });
    });

    /* Reaktionen ohne Neuladen */
    verlauf.addEventListener('submit', function (e) {
        var f = e.target.closest('form[data-reaktion]');
        if (!f) return;
        e.preventDefault();
        fetch(f.action, { method: 'POST', credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf }, body: new FormData(f) })
            .then(function (r) { return r.json(); })
            .then(function (j) { var box = f.closest('[data-reaktionen]'); if (box && j.html) { var t = document.createElement('div'); t.innerHTML = j.html; box.replaceWith(t.firstElementChild); } });
    });

    /* Sprachnachricht */
    var knopf = form.querySelector('[data-sprache]');
    var leiste = form.querySelector('[data-aufnahme]');
    var zeit = form.querySelector('[data-aufnahme-zeit]');
    var rec = null, teile = [], start = 0, uhr = null, verwerfen = false;
    if (!navigator.mediaDevices || !window.MediaRecorder) { knopf.hidden = true; }
    function stopp(weg) {
        verwerfen = !!weg;
        if (rec && rec.state !== 'inactive') rec.stop();
    }
    knopf.addEventListener('click', function () {
        if (rec && rec.state === 'recording') { stopp(false); return; }
        navigator.mediaDevices.getUserMedia({ audio: true }).then(function (stream) {
            var typ = ['audio/webm;codecs=opus', 'audio/webm', 'audio/mp4', 'audio/ogg'].find(function (t) { return MediaRecorder.isTypeSupported(t); }) || '';
            rec = new MediaRecorder(stream, typ ? { mimeType: typ } : {});
            teile = []; verwerfen = false; start = Date.now();
            rec.ondataavailable = function (e) { if (e.data.size) teile.push(e.data); };
            rec.onstop = function () {
                stream.getTracks().forEach(function (t) { t.stop(); });
                clearInterval(uhr); leiste.hidden = true; knopf.classList.remove('text-danger');
                if (verwerfen || !teile.length) return;
                var blob = new Blob(teile, { type: rec.mimeType || 'audio/webm' });
                var fd = new FormData();
                fd.append('_token', csrf);
                fd.append('audio', blob, 'sprachnachricht.' + ((rec.mimeType || '').indexOf('mp4') >= 0 ? 'm4a' : 'webm'));
                fd.append('sek', Math.round((Date.now() - start) / 1000));
                senden(fd).catch(function (err) { alert(err.message); });
            };
            rec.start();
            leiste.hidden = false; knopf.classList.add('text-danger');
            uhr = setInterval(function () { var s = Math.round((Date.now() - start) / 1000); zeit.textContent = Math.floor(s / 60) + ':' + ('0' + (s % 60)).slice(-2); }, 500);
        }).catch(function () { alert('Kein Zugriff auf das Mikrofon. Erlaube es in den Einstellungen des Browsers.'); });
    });
    form.querySelector('[data-aufnahme-stopp]').addEventListener('click', function () { stopp(false); });
    form.querySelector('[data-aufnahme-abbruch]').addEventListener('click', function () { stopp(true); });
})();

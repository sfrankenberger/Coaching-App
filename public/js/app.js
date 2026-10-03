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
            /* Stelle und Kapitel gelten je Video der Playlist */
            if (wahl.dataset.medien) { player.dataset.medien = wahl.dataset.medien; player.dataset.start = wahl.dataset.start || '0'; }
            document.querySelectorAll('[data-video-info]').forEach(function (b) { b.hidden = b.dataset.videoInfo !== (wahl.dataset.index || '0'); });
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

    /* Der Verlauf scrollt selbst (Kopf und Eingabe bleiben stehen); als Netz auch die Seite */
    function nachUnten() {
        verlauf.scrollTo({ top: verlauf.scrollHeight, behavior: 'smooth' });
        if (verlauf.scrollHeight <= verlauf.clientHeight + 2) window.scrollTo({ top: document.body.scrollHeight, behavior: 'smooth' });
    }
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
    verlauf.scrollTop = verlauf.scrollHeight;

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
    var takt = setInterval(nachfragen, 5000);
    window.gespraechNachfragen = nachfragen;
    /* Mit Reverb kommt die Nachricht sofort, dann reicht seltenes Nachfragen als Netz */
    window.gespraechTakt = function (ms) { clearInterval(takt); takt = setInterval(nachfragen, ms); };
    document.addEventListener('visibilitychange', function () { if (!document.hidden) nachfragen(); });

    /* Senden */
    var textarea = form.querySelector('textarea[name="body"]');
    var dateiInput = form.querySelector('input[name="file"]');
    var dateiName = form.querySelector('[data-datei-name]');
    var anhangBox = form.querySelector('[data-chat-anhang]');
    var wahl = form.querySelector('[data-anhang-wahl]');
    /* Beim Schreiben bekommt das Feld die ganze Breite: Aufnahme und Diktat weichen, bis es wieder leer ist */
    function hatText() { form.classList.toggle('hat-text', textarea.value.trim() !== ''); }
    textarea.addEventListener('input', function () { textarea.style.height = 'auto'; textarea.style.height = Math.min(160, textarea.scrollHeight) + 'px'; hatText(); });
    hatText();
    textarea.addEventListener('keydown', function (e) { if (e.key === 'Enter' && (e.metaKey || e.ctrlKey)) { e.preventDefault(); form.requestSubmit(); } });
    dateiInput.addEventListener('change', function () {
        if (dateiInput.files.length) { dateiName.hidden = false; dateiName.textContent = '📎 ' + dateiInput.files[0].name; } else { dateiName.hidden = true; }
    });
    var plus = form.querySelector('[data-anhang-plus]');
    if (plus && anhangBox) plus.addEventListener('click', function () { anhangBox.hidden = !anhangBox.hidden; });
    function hatAnhang() { return dateiInput.files.length || form.querySelector('input[name="refs[]"]'); }
    function aufraeumen() { form.classList.remove('hat-text');
        textarea.value = ''; textarea.style.height = 'auto'; dateiInput.value = ''; dateiName.hidden = true;
        if (wahl && wahl.leeren) wahl.leeren();
        if (anhangBox) anhangBox.hidden = true;
    }
    function senden(fd) {
        return fetch(form.action, { method: 'POST', credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf }, body: fd })
            .then(function (r) { return r.json().then(function (j) { if (!r.ok) throw new Error(j.fehler || 'Senden fehlgeschlagen'); return j; }); })
            .then(function (j) { verlauf.dataset.letzte = j.id; anhaengen(j.html); });
    }
    /* Trenner "Neu" verblasst nach ein paar Sekunden, die Markierung bleibt */
    var trenner = verlauf.querySelector('[data-neu-trenner]');
    if (trenner) { trenner.scrollIntoView({ block: 'center' }); setTimeout(function () { trenner.classList.add('weg'); }, 5000); }
    /* Waechst die Eingabe (Anhang, Aufnahme, langer Text), bleibt die letzte Nachricht sichtbar */
    if (window.ResizeObserver) new ResizeObserver(function () { if (verlauf.scrollHeight - verlauf.scrollTop - verlauf.clientHeight < 160) verlauf.scrollTop = verlauf.scrollHeight; }).observe(form);
    /* Nur ein Player gleichzeitig, Hinweis wenn eine Aufnahme nicht abspielbar ist */
    verlauf.addEventListener('play', function (e) {
        if (!e.target.matches('audio, video')) return;
        verlauf.querySelectorAll('audio, video').forEach(function (a) { if (a !== e.target) a.pause(); });
    }, true);
    verlauf.addEventListener('error', function (e) {
        if (!e.target.matches('audio')) return;
        var h = document.createElement('span'); h.className = 'hinweis block'; h.textContent = 'Diese Aufnahme lässt sich hier nicht abspielen.';
        e.target.insertAdjacentElement('afterend', h);
    }, true);

    var sendeKnopf = form.querySelector('button[type="submit"]'), sendet = false;
    function vorschau(text) {
        var d = document.createElement('div'); d.className = 'flex items-end gap-2 justify-end'; d.setAttribute('data-vorschau', '1');
        d.innerHTML = '<div class="blase blase-meine blase-sendet"><div class="lesetext whitespace-pre-line break-words"></div><div class="blase-zeit"><span>wird gesendet ...</span></div></div>';
        d.querySelector('.lesetext').textContent = text || '📎';
        verlauf.appendChild(d); nachUnten();
        return d;
    }
    form.addEventListener('submit', function (e) {
        e.preventDefault();
        if (sendet) return;
        var fd = new FormData(form);
        if (!textarea.value.trim() && !hatAnhang()) return;
        sendet = true; if (sendeKnopf) sendeKnopf.disabled = true;
        var v = vorschau(textarea.value.trim());
        senden(fd).then(function () { v.remove(); aufraeumen(); }).catch(function (err) { v.querySelector('.blase').classList.add('blase-fehler'); v.querySelector('.blase-zeit span').textContent = err.message; setTimeout(function () { v.remove(); }, 4000); })
            .finally(function () { sendet = false; if (sendeKnopf) sendeKnopf.disabled = false; });
    });

    /* Diktieren: Gesprochenes wird zu Text im Feld */
    var diktat = form.querySelector('[data-diktat]');
    var SR = window.SpeechRecognition || window.webkitSpeechRecognition;
    if (diktat) {
        if (!SR) { diktat.hidden = true; }
        else {
            var erk = null;
            diktat.addEventListener('click', function () {
                if (erk) { erk.stop(); return; }
                var hatte = false, los = Date.now();
                erk = new SR(); erk.lang = document.documentElement.lang || 'de-CH'; erk.interimResults = false; erk.continuous = true;
                erk.onresult = function (e) {
                    var neu = '';
                    for (var i = e.resultIndex; i < e.results.length; i++) if (e.results[i].isFinal) neu += e.results[i][0].transcript;
                    if (!neu) return;
                    hatte = true;
                    var vor = textarea.value && !/\s$/.test(textarea.value) ? ' ' : '';
                    textarea.value += vor + neu.trim();
                    textarea.dispatchEvent(new Event('input', { bubbles: true }));
                };
                erk.onend = function () { erk = null; diktat.classList.remove('text-danger'); form.classList.remove('diktiert'); if (!hatte && Date.now() - los < 1500) window.leaDiktatHinweis(form); };
                erk.onerror = function (e) { erk = null; diktat.classList.remove('text-danger'); form.classList.remove('diktiert'); window.leaDiktatHinweis(form, e && e.error); };
                try { erk.start(); diktat.classList.add('text-danger'); form.classList.add('diktiert'); } catch (e) { erk = null; form.classList.remove('diktiert'); window.leaDiktatHinweis(form, e && e.name); }
            });
        }
    }

    /* Sprachnachricht: aufnehmen, kurz reinhoeren, dann senden oder verwerfen */
    var knopf = form.querySelector('[data-sprache]');
    var leiste = form.querySelector('[data-aufnahme]');
    var zeit = form.querySelector('[data-aufnahme-zeit]');
    var probe = form.querySelector('[data-probe]'), probeAudio = form.querySelector('[data-probe-audio]'), probeDauer = form.querySelector('[data-probe-dauer]');
    var rec = null, teile = [], start = 0, uhr = null, verwerfen = false, probeBlob = null, probeSek = 0, probeUrl = null;
    if (!navigator.mediaDevices || !window.MediaRecorder) { knopf.hidden = true; }
    function stopp(weg) {
        verwerfen = !!weg;
        if (rec && rec.state !== 'inactive') rec.stop();
    }
    function probeWeg() {
        probe.hidden = true; probeBlob = null;
        if (probeUrl) { URL.revokeObjectURL(probeUrl); probeUrl = null; }
        probeAudio.removeAttribute('src'); probeAudio.load();
    }
    function mmss(s) { return Math.floor(s / 60) + ':' + ('0' + (s % 60)).slice(-2); }
    knopf.addEventListener('click', function () {
        if (rec && rec.state === 'recording') { stopp(false); return; }
        probeWeg();
        navigator.mediaDevices.getUserMedia({ audio: true }).then(function (stream) {
            var typ = ['audio/webm;codecs=opus', 'audio/webm', 'audio/mp4', 'audio/ogg'].find(function (t) { return MediaRecorder.isTypeSupported(t); }) || '';
            rec = new MediaRecorder(stream, typ ? { mimeType: typ } : {});
            teile = []; verwerfen = false; start = Date.now();
            rec.ondataavailable = function (e) { if (e.data.size) teile.push(e.data); };
            rec.onstop = function () {
                stream.getTracks().forEach(function (t) { t.stop(); });
                clearInterval(uhr); leiste.hidden = true; knopf.classList.remove('text-danger');
                if (verwerfen || !teile.length) return;
                probeBlob = new Blob(teile, { type: rec.mimeType || 'audio/webm' });
                probeSek = Math.round((Date.now() - start) / 1000);
                probeUrl = URL.createObjectURL(probeBlob);
                probeAudio.src = probeUrl; probeDauer.textContent = mmss(probeSek);
                probe.hidden = false;
            };
            rec.start();
            leiste.hidden = false; knopf.classList.add('text-danger');
            uhr = setInterval(function () { zeit.textContent = mmss(Math.round((Date.now() - start) / 1000)); }, 500);
        }).catch(function () { alert('Kein Zugriff auf das Mikrofon. Erlaube es in den Einstellungen des Browsers.'); });
    });
    form.querySelector('[data-aufnahme-stopp]').addEventListener('click', function () { stopp(false); });
    form.querySelector('[data-aufnahme-abbruch]').addEventListener('click', function () { stopp(true); });
    form.querySelector('[data-probe-weg]').addEventListener('click', probeWeg);
    form.querySelector('[data-probe-senden]').addEventListener('click', function () {
        if (!probeBlob) return;
        var fd = new FormData();
        fd.append('_token', csrf);
        fd.append('audio', probeBlob, 'sprachnachricht.' + ((probeBlob.type || '').indexOf('mp4') >= 0 ? 'm4a' : 'webm'));
        fd.append('sek', probeSek);
        var b = this; b.disabled = true;
        senden(fd).then(probeWeg).catch(function (err) { alert(err.message); }).finally(function () { b.disabled = false; });
    });
})();

/* ---------- Push-Nachrichten einschalten ---------- */
(function () {
    var box = document.querySelector('[data-push]');
    if (!box) return;
    var an = box.querySelector('[data-push-an]'), aus = box.querySelector('[data-push-aus]'), status = box.querySelector('[data-push-status]'), test = box.querySelector('[data-push-test]');
    var csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    if (test) test.addEventListener('click', function () {
        status.textContent = 'Schicke Test ...';
        fetch(test.dataset.pushTest, { method: 'POST', credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf } })
            .then(function (r) { return r.json(); }).then(function (j) { status.textContent = j.meldung || (j.ok ? 'Test ist raus.' : 'Das hat nicht geklappt.'); })
            .catch(function () { status.textContent = 'Das hat nicht geklappt.'; });
    });
    if (!('serviceWorker' in navigator) || !('PushManager' in window) || !('Notification' in window)) {
        an.hidden = true; status.textContent = 'Dieser Browser kann keine Push-Nachrichten. Auf dem iPhone: zuerst den Bereich auf den Startbildschirm legen und von dort öffnen.';
        return;
    }
    function b64(s) { var p = '='.repeat((4 - s.length % 4) % 4); var r = (s + p).replace(/-/g, '+').replace(/_/g, '/'); var raw = atob(r); return Uint8Array.from(raw, function (c) { return c.charCodeAt(0); }); }
    an.addEventListener('click', function () {
        status.textContent = 'Einen Moment ...';
        Notification.requestPermission().then(function (perm) {
            if (perm !== 'granted') { status.textContent = 'Push wurde nicht erlaubt. Du kannst es in den Einstellungen des Browsers ändern.'; return; }
            return navigator.serviceWorker.register('/sw.js').then(function () { return navigator.serviceWorker.ready; })
                .then(function (reg) {
                    return fetch(box.dataset.schluessel, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } }).then(function (r) { return r.json(); })
                        .then(function (j) { if (!j.publicKey) throw new Error(j.fehler || 'Kein Schlüssel'); return reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: b64(j.publicKey) }); });
                })
                .then(function (sub) {
                    return fetch(box.dataset.abo, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf }, body: JSON.stringify(sub.toJSON()) }).then(function (r) { return r.json(); });
                })
                .then(function (j) { status.textContent = 'Push ist an. ' + (j.anzahl || 1) + ' Gerät' + (j.anzahl > 1 ? 'e' : '') + ' angemeldet.'; aus.hidden = false; });
        }).catch(function (e) { status.textContent = 'Das hat nicht geklappt: ' + e.message; });
    });
    aus.addEventListener('click', function () {
        navigator.serviceWorker.ready.then(function (reg) { return reg.pushManager.getSubscription(); }).then(function (sub) {
            var endpoint = sub ? sub.endpoint : '';
            if (sub) sub.unsubscribe();
            return fetch(box.dataset.abo, { method: 'DELETE', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf }, body: JSON.stringify({ endpoint: endpoint }) }).then(function (r) { return r.json(); });
        }).then(function (j) { status.textContent = j.anzahl ? j.anzahl + ' Gerät(e) angemeldet' : 'Push ist aus.'; if (!j.anzahl) aus.hidden = true; }).catch(function () {});
    });
})();

/* Merken: Lesezeichen ohne Neuladen umschalten */
(function () {
    var csrf = document.querySelector('meta[name="csrf-token"]');
    csrf = csrf ? csrf.getAttribute('content') : '';
    document.addEventListener('submit', function (e) {
        var f = e.target.closest('form[data-merken]');
        if (!f) return;
        e.preventDefault();
        var b = f.querySelector('button');
        fetch(f.action, { method: 'POST', credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf }, body: new FormData(f) })
            .then(function (r) { return r.json(); })
            .then(function (j) {
                var an = !!j.an;
                b.dataset.an = an ? '1' : '0';
                b.classList.toggle('an', an);
                if (b.classList.contains('knopf')) { b.classList.toggle('text-primary', an); b.classList.toggle('border-primary', an); }
                var i = b.querySelector('i'); if (i) { i.classList.toggle('fa-solid', an); i.classList.toggle('fa-regular', !an); }
                var t = b.querySelector('[data-merken-text]'); if (t) t.textContent = an ? 'Gemerkt' : 'Merken';
                b.setAttribute('aria-pressed', an ? 'true' : 'false');
            })
            .catch(function () { f.submit(); });
    });
})();

/* Link kopieren */
document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-kopieren]'); if (!b) return;
    var alt = b.textContent;
    (navigator.clipboard ? navigator.clipboard.writeText(b.dataset.kopieren) : Promise.reject()).then(function () { b.textContent = 'Kopiert'; setTimeout(function () { b.textContent = alt; }, 1500); }).catch(function () { window.prompt('Link kopieren:', b.dataset.kopieren); });
});

/* ---------- Menue von links ---------- */
(function () {
    var menue = document.getElementById('menue');
    if (!menue) return;
    var schleier = document.querySelector('.schleier-menue');
    var burger = document.querySelector('[data-menue-auf]');
    function setzen(auf) {
        menue.classList.toggle('offen', auf);
        schleier.classList.toggle('offen', auf);
        menue.setAttribute('aria-hidden', auf ? 'false' : 'true');
        if (burger) burger.setAttribute('aria-expanded', auf ? 'true' : 'false');
        document.body.classList.toggle('gesperrt', auf);
    }
    document.addEventListener('click', function (e) {
        if (e.target.closest('[data-menue-auf]')) { setzen(true); return; }
        if (e.target.closest('[data-menue-zu]')) { setzen(false); return; }
        if (e.target.closest('#menue a')) setzen(false);
    });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') setzen(false); });
})();

/* ---------- Chatknopf beim Runterscrollen ausblenden ---------- */
(function () {
    var k = document.querySelector('.chat-knopf');
    if (!k) return;
    var letzte = window.scrollY;
    window.addEventListener('scroll', function () {
        var y = window.scrollY;
        k.classList.toggle('weg', y > letzte && y > 160);
        letzte = y;
    }, { passive: true });
})();

/* ---------- Ziehen zum Aktualisieren (nur mobil) ---------- */
(function () {
    var anzeige = document.querySelector('.pull');
    if (!anzeige || !('ontouchstart' in window)) return;
    var start = null, weg = 0, SCHWELLE = 72, MAX = 90;
    function offenesFenster() { return document.body.classList.contains('gesperrt'); }
    document.addEventListener('touchstart', function (e) {
        if (window.innerWidth > 760 || window.scrollY > 0 || offenesFenster()) { start = null; return; }
        if (e.target.closest('textarea, input, select, .video, iframe')) { start = null; return; }
        start = e.touches[0].clientY; weg = 0;
    }, { passive: true });
    document.addEventListener('touchmove', function (e) {
        if (start === null) return;
        weg = Math.max(0, (e.touches[0].clientY - start) * 0.55);
        if (window.scrollY > 0) { weg = 0; }
        anzeige.style.height = Math.min(MAX, weg) + 'px';
        anzeige.querySelector('i').style.transform = 'rotate(' + Math.round(weg * 3) + 'deg)';
    }, { passive: true });
    document.addEventListener('touchend', function () {
        if (start === null) return;
        start = null;
        if (weg >= SCHWELLE) {
            anzeige.classList.add('laedt');
            anzeige.style.height = '48px';
            var verlauf = document.getElementById('verlauf');
            if (verlauf && window.gespraechNachfragen) {
                window.gespraechNachfragen();
                setTimeout(function () { anzeige.classList.remove('laedt'); anzeige.style.height = '0'; }, 700);
            } else {
                location.reload();
            }
        } else {
            anzeige.style.height = '0';
        }
    });
})();

/* ---------- Hinweis-Fenster: App installieren und Push einschalten ---------- */
(function () {
    var sheet = document.getElementById('app-sheet');
    if (!sheet) return;
    var inhalt = sheet.querySelector('[data-sheet-inhalt]');
    var csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    var coach = sheet.dataset.coach || 'deiner Coachin';
    var heute = new Date().toISOString().slice(0, 10);
    function merk(k, v) { try { if (v === undefined) return localStorage.getItem(k); localStorage.setItem(k, v); } catch (e) { return null; } }
    function auf(html) { inhalt.innerHTML = html; sheet.classList.add('offen'); sheet.setAttribute('aria-hidden', 'false'); document.body.classList.add('gesperrt'); }
    function zu() { sheet.classList.remove('offen'); sheet.setAttribute('aria-hidden', 'true'); document.body.classList.remove('gesperrt'); }
    window.appSheet = { auf: auf, zu: zu };
    sheet.addEventListener('click', function (e) { if (e.target.closest('[data-sheet-zu]')) zu(); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') zu(); });

    var standalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
    var ios = /iphone|ipad|ipod/i.test(navigator.userAgent) && !window.MSStream;
    var installEvent = null;
    window.addEventListener('beforeinstallprompt', function (e) { e.preventDefault(); installEvent = e; });

    function b64(s) { var p = '='.repeat((4 - s.length % 4) % 4); var raw = atob((s + p).replace(/-/g, '+').replace(/_/g, '/')); return Uint8Array.from(raw, function (c) { return c.charCodeAt(0); }); }
    function pushEinschalten(btn) {
        btn.disabled = true; btn.textContent = 'Einen Moment ...';
        Notification.requestPermission().then(function (perm) {
            if (perm !== 'granted') throw new Error('nicht erlaubt');
            return navigator.serviceWorker.ready;
        }).then(function (reg) {
            return fetch(sheet.dataset.pushSchluessel, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } }).then(function (r) { return r.json(); })
                .then(function (j) { return reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: b64(j.publicKey) }); });
        }).then(function (sub) {
            return fetch(sheet.dataset.pushAbo, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf }, body: JSON.stringify(sub.toJSON()) });
        }).then(function () {
            auf('<h3>Push ist an</h3><p>Du bekommst ab jetzt einen kurzen Hinweis, wenn ' + coach + ' dir schreibt oder etwas Neues für dich da ist.</p><button type="button" class="knopf knopf-breit" data-sheet-zu>Schön</button>');
        }).catch(function () {
            auf('<h3>Das hat nicht geklappt</h3><p>Du kannst Push jederzeit im Profil einschalten. Falls dein Browser nachfragt, tippe auf «Erlauben».</p><button type="button" class="knopf knopf-breit" data-sheet-zu>Okay</button>');
        });
    }

    sheet.addEventListener('click', function (e) {
        var b = e.target.closest('[data-sheet-aktion]');
        if (!b) return;
        var a = b.dataset.sheetAktion;
        if (a === 'installieren' && installEvent) { installEvent.prompt(); installEvent.userChoice.finally(function () { installEvent = null; zu(); }); }
        if (a === 'push') pushEinschalten(b);
        if (a === 'nicht') { merk('app-sheet-nicht', heute); zu(); }
    });

    /* Nur auf der Startseite, hoechstens einmal pro Tag */
    if (sheet.dataset.start !== '1' || merk('app-sheet-nicht') === heute) return;
    setTimeout(function () {
        var pushGeht = 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;
        if (standalone && pushGeht && Notification.permission === 'default') {
            auf('<h3>Soll ich dir Bescheid geben?</h3><p>Wenn ' + coach + ' dir schreibt oder etwas Neues für dich da ist, bekommst du einen kurzen Hinweis aufs Handy. Ein paar pro Woche, nicht mehr.</p>'
                + '<button type="button" class="knopf knopf-breit" data-sheet-aktion="push">Push einschalten</button><button type="button" class="knopf knopf-text knopf-breit" data-sheet-aktion="nicht">Jetzt nicht</button>');
        } else if (!standalone && installEvent) {
            auf('<h3>Als App auf den Startbildschirm</h3><p>Dann bist du mit einem Tipp hier, ohne Anmelden und ohne Suchen.</p>'
                + '<button type="button" class="knopf knopf-breit" data-sheet-aktion="installieren">Installieren</button><button type="button" class="knopf knopf-text knopf-breit" data-sheet-aktion="nicht">Jetzt nicht</button>');
        } else if (!standalone && ios && window.innerWidth < 760) {
            auf('<h3>Als App auf den Startbildschirm</h3><p>Dann bist du mit einem Tipp hier, und Push-Nachrichten gehen auch.</p>'
                + '<div class="schritt"><b>1</b><span>Tippe unten auf <i class="fa-solid fa-arrow-up-from-bracket"></i> Teilen.</span></div>'
                + '<div class="schritt"><b>2</b><span>Wähle «Zum Home-Bildschirm».</span></div>'
                + '<div class="schritt"><b>3</b><span>Tippe auf «Hinzufügen» und öffne die App von dort.</span></div>'
                + '<button type="button" class="knopf knopf-text knopf-breit" data-sheet-aktion="nicht">Jetzt nicht</button>');
        }
    }, 1200);
})();

/* Technik-Hilfe: Geraet und Bildschirm mitschicken */
(function () {
    var f = document.querySelector('form[data-hilfe]');
    if (!f) return;
    var g = f.querySelector('input[name="geraet"]');
    var standalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
    g.value = screen.width + 'x' + screen.height + ' @' + (window.devicePixelRatio || 1) + ', Fenster ' + window.innerWidth + 'x' + window.innerHeight + (standalone ? ', als App' : ', im Browser') + ', ' + (navigator.language || '');
})();

/* Sprungmarken "(ab 12:34)": springt im Video der Seite an die Stelle */
document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-sprung]');
    if (!b) return;
    var s = parseInt(b.dataset.sprung, 10) || 0;
    var box = document.getElementById('video-player') || document.querySelector('.video');
    if (!box) return;
    var v = box.querySelector('video, audio');
    var f = box.querySelector('iframe');
    if (v) { v.currentTime = s; v.play(); }
    else if (f && /vimeo/.test(f.src)) {
        f.contentWindow.postMessage(JSON.stringify({ method: 'setCurrentTime', value: s }), '*');
        f.contentWindow.postMessage(JSON.stringify({ method: 'play' }), '*');
    } else if (f && /youtube/.test(f.src)) {
        f.contentWindow.postMessage(JSON.stringify({ event: 'command', func: 'seekTo', args: [s, true] }), '*');
        f.contentWindow.postMessage(JSON.stringify({ event: 'command', func: 'playVideo', args: [] }), '*');
    } else if (f) {
        f.src = f.src.replace(/#t=\d+s?$/, '') + '#t=' + s + 's';
    }
    if (b.dataset.ohneScroll === undefined) box.scrollIntoView({ behavior: 'smooth', block: 'center' });
});

/* Kapitelliste laeuft mit: die Medienbeobachtung meldet die Zeit ueber 'medien:zeit' */
document.addEventListener('medien:zeit', function (e) {
    var liste = Array.prototype.find.call(document.querySelectorAll('[data-kapitel]'), function (l) { return !l.closest('[hidden]'); });
    if (!liste) return;
    var s = e.detail.sekunden, aktiv = null;
    var zeilen = liste.querySelectorAll('li');
    zeilen.forEach(function (li) {
        var t = parseInt(li.querySelector('[data-sprung]').dataset.sprung, 10) || 0;
        li.classList.remove('aktiv', 'vorbei');
        if (t <= s) { if (aktiv) aktiv.classList.add('vorbei'); aktiv = li; }
    });
    if (aktiv) aktiv.classList.add('aktiv');
});

/* ---------- Videoposition merken, ab 80 % erledigt ---------- */
(function () {
    var boxen = document.querySelectorAll('[data-medien]');
    if (!boxen.length) return;
    var csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    var ziel = document.querySelector('meta[name="medien-url"]');
    var url = ziel ? ziel.content : '/medien/position';

    function senden(key, s, d) {
        return fetch(url, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf }, body: JSON.stringify({ key: key, seconds: Math.floor(s), duration: d ? Math.floor(d) : null }) })
            .then(function (r) { return r.json(); })
            .then(function (j) { if (j.erledigt) { var h = document.querySelector('[data-erledigt-hinweis]'); if (h) h.hidden = false; } })
            .catch(function () {});
    }
    function beobachten(box) {
        var zuletzt = 0;
        function key() { return box.dataset.medien; }
        function start() { return parseInt(box.dataset.start || '0', 10); }
        function melden(s, d, sofort) {
            document.dispatchEvent(new CustomEvent('medien:zeit', { detail: { key: key(), sekunden: s } }));
            if (!sofort && Math.abs(s - zuletzt) < 10) return;
            zuletzt = s; senden(key(), s, d);
        }
        var v = box.querySelector('video, audio');
        if (v) {
            v.addEventListener('loadedmetadata', function () { var st = start(); if (st > 5 && st < v.duration - 10) v.currentTime = st; });
            v.addEventListener('timeupdate', function () { melden(v.currentTime, v.duration, false); });
            v.addEventListener('pause', function () { melden(v.currentTime, v.duration, true); });
            v.addEventListener('ended', function () { melden(v.duration, v.duration, true); });
            return;
        }
        var f = box.querySelector('iframe');
        if (!f || !/vimeo/.test(f.src)) return;
        var dauer = 0, gesprungen = false;
        function an(msg) { f.contentWindow.postMessage(JSON.stringify(msg), '*'); }
        window.addEventListener('message', function (e) {
            if (e.source !== f.contentWindow || !/vimeo\.com$/.test(new URL(e.origin).hostname)) return;
            var d; try { d = typeof e.data === 'string' ? JSON.parse(e.data) : e.data; } catch (x) { return; }
            if (!d) return;
            if (d.event === 'ready') {
                ['timeupdate', 'pause', 'ended'].forEach(function (ev) { an({ method: 'addEventListener', value: ev }); });
                if (start() > 5 && !gesprungen) { gesprungen = true; an({ method: 'setCurrentTime', value: start() }); }
            }
            if (d.event === 'timeupdate' && d.data) { dauer = d.data.duration || dauer; melden(d.data.seconds, dauer, false); }
            if (d.event === 'pause' && d.data) melden(d.data.seconds, dauer, true);
            if (d.event === 'ended') melden(dauer, dauer, true);
        });
        /* Falls "ready" schon vorbei ist, bevor wir lauschen: nach dem Laden direkt anmelden */
        f.addEventListener('load', function () {
            gesprungen = false; zuletzt = 0;
            ['timeupdate', 'pause', 'ended'].forEach(function (ev) { an({ method: 'addEventListener', value: ev }); });
            if (start() > 5 && !gesprungen) { gesprungen = true; setTimeout(function () { an({ method: 'setCurrentTime', value: start() }); }, 600); }
        });
    }
    boxen.forEach(beobachten);
})();

/* Diktieren: auf dem iPhone geht die Spracherkennung nur in Safari, nicht in der installierten App (Home-Bildschirm).
   Dann bleibt der Knopf stumm, darum ein Hinweis mit dem Ausweg: Mikrofon auf der Tastatur. */
window.leaDiktatHinweis = function (behaelter, grund) {
    var alt = behaelter.querySelector('.diktat-hinweis');
    if (alt) alt.remove();
    var p = document.createElement('p');
    p.className = 'hinweis diktat-hinweis';
    p.style.cssText = 'margin:6px 0 0;flex-basis:100%;width:100%';
    var warum = grund === 'not-allowed' || grund === 'service-not-allowed' ? 'Mikrofon oder Spracherkennung ist für die App nicht erlaubt (iPhone: Einstellungen, Apps, Safari, Mikrofon)'
        : grund === 'audio-capture' ? 'kein Mikrofon gefunden'
        : grund === 'network' ? 'keine Verbindung zur Spracherkennung'
        : (grund ? grund : 'die Spracherkennung hat nicht geantwortet');
    p.textContent = 'Diktieren hat nicht geklappt: ' + warum + '. Tipp: das Mikrofon auf der Tastatur benutzen, das schreibt direkt ins Feld.';
    behaelter.appendChild(p);
    setTimeout(function () { if (p.parentNode) p.remove(); }, 15000);
};

/* ---------- Diktieren: Mikrofon an Textfeldern (Web Speech API) ---------- */
(function () {
    var SR = window.SpeechRecognition || window.webkitSpeechRecognition;
    if (!SR) return;
    var sprache = document.documentElement.lang || 'de-CH';
    document.querySelectorAll('textarea.feld').forEach(function (t) {
        if (t.closest('.chat-eingabe') || t.dataset.ohneDiktat !== undefined) return;
        var wrap = t.parentNode;
        if (!wrap.classList.contains('mit-mikro')) {
            wrap = document.createElement('div');
            wrap.className = 'mit-mikro';
            t.parentNode.insertBefore(wrap, t);
            wrap.appendChild(t);
        }
        var b = document.createElement('button');
        b.type = 'button'; b.className = 'mikro'; b.setAttribute('aria-label', 'Diktieren'); b.title = 'Diktieren';
        b.innerHTML = '<i class="fa-solid fa-microphone"></i>';
        wrap.appendChild(b);
        var rec = null;
        b.addEventListener('click', function () {
            if (rec) { rec.stop(); return; }
            var hatte = false, los = Date.now();
            rec = new SR(); rec.lang = sprache; rec.interimResults = false; rec.continuous = true;
            rec.onresult = function (e) {
                var neu = '';
                for (var i = e.resultIndex; i < e.results.length; i++) if (e.results[i].isFinal) neu += e.results[i][0].transcript;
                if (!neu) return;
                hatte = true;
                var vor = t.value && !/\s$/.test(t.value) ? ' ' : '';
                t.value += vor + neu.trim();
                t.dispatchEvent(new Event('input', { bubbles: true }));
            };
            rec.onend = function () { rec = null; b.classList.remove('an'); t.dispatchEvent(new Event('focusout', { bubbles: true })); if (!hatte && Date.now() - los < 1500) window.leaDiktatHinweis(wrap); };
            rec.onerror = function (e) { rec = null; b.classList.remove('an'); window.leaDiktatHinweis(wrap, e && e.error); };
            try { rec.start(); b.classList.add('an'); } catch (e) { rec = null; window.leaDiktatHinweis(wrap, e && e.name); }
        });
    });
})();

/* ---------- Reaktionen an geteilten Eintraegen ohne Neuladen ---------- */
(function () {
    var csrf = document.querySelector('meta[name="csrf-token"]');
    if (!csrf) return;
    csrf = csrf.getAttribute('content');
    document.addEventListener('submit', function (e) {
        var f = e.target.closest('form[data-reaktion-allgemein]');
        if (!f) return;
        e.preventDefault();
        fetch(f.action, { method: 'POST', credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf }, body: new FormData(f) })
            .then(function (r) { return r.json(); })
            .then(function (j) { var box = f.closest('[data-reaktionen-allgemein]'); if (box && j.html) { var t = document.createElement('div'); t.innerHTML = j.html; box.replaceWith(t.firstElementChild); } })
            .catch(function () {});
    });
})();

/* ---------- Etwas anhaengen: Auswahl, Suche, Chips ---------- */
(function () {
    document.querySelectorAll('[data-anhang-wahl]').forEach(function (box) {
        var name = box.dataset.name || 'refs', mehrfach = box.dataset.mehrfach === '1';
        var auf = box.querySelector('[data-anhang-auf]'), zu = box.querySelector('[data-anhang-zu]');
        var panel = box.querySelector('[data-anhang-panel]'), karten = box.querySelector('[data-anhang-karten]');
        var suche = box.querySelector('[data-anhang-suche]'), gewaehlt = box.querySelector('[data-anhang-gewaehlt]');
        var auswahl = [];
        try { auswahl = JSON.parse(box.dataset.auswahl || '[]'); } catch (e) {}
        var timer = null;
        function refs() { return Array.prototype.map.call(gewaehlt.querySelectorAll('.anhang-chip'), function (c) { return c.dataset.ref; }); }
        function zeichnen(liste) {
            var da = refs();
            karten.innerHTML = '';
            if (!liste.length) { var l = document.createElement('p'); l.className = 'leer m-0'; l.textContent = 'Nichts gefunden.'; karten.appendChild(l); return; }
            liste.forEach(function (k) {
                var b = document.createElement('button'); b.type = 'button'; b.dataset.ref = k.ref;
                if (da.indexOf(k.ref) >= 0) b.classList.add('an');
                b.innerHTML = '<i class="fa-solid fa-' + k.icon + '"></i><span><b></b><em></em></span>';
                b.querySelector('b').textContent = k.label + (k.zusatz ? ' · ' + k.zusatz : '');
                var em = b.querySelector('em'); em.style.fontStyle = 'normal'; em.textContent = k.titel;
                b.addEventListener('click', function () { waehlen(k); });
                karten.appendChild(b);
            });
        }
        function waehlen(k) {
            if (refs().indexOf(k.ref) >= 0) { entfernen(k.ref); return; }
            if (!mehrfach) gewaehlt.innerHTML = '';
            var c = document.createElement('span'); c.className = 'anhang-chip'; c.dataset.ref = k.ref;
            c.innerHTML = '<i class="fa-solid fa-' + k.icon + '"></i><span></span><button type="button" data-weg aria-label="Entfernen">&times;</button><input type="hidden">';
            c.querySelector('span').textContent = k.label + ' · ' + (k.titel.length > 40 ? k.titel.slice(0, 39) + '…' : k.titel);
            var inp = c.querySelector('input'); inp.name = name + '[]'; inp.value = k.ref;
            gewaehlt.appendChild(c); gewaehlt.hidden = false;
            karten.querySelectorAll('button').forEach(function (b) { if (b.dataset.ref === k.ref) b.classList.add('an'); });
            if (!mehrfach) { panel.hidden = true; }
            box.dispatchEvent(new CustomEvent('anhang', { bubbles: true }));
        }
        function entfernen(ref) {
            gewaehlt.querySelectorAll('.anhang-chip').forEach(function (c) { if (c.dataset.ref === ref) c.remove(); });
            if (!gewaehlt.querySelector('.anhang-chip')) gewaehlt.hidden = true;
            karten.querySelectorAll('button').forEach(function (b) { if (b.dataset.ref === ref) b.classList.remove('an'); });
            box.dispatchEvent(new CustomEvent('anhang', { bubbles: true }));
        }
        gewaehlt.addEventListener('click', function (e) { var w = e.target.closest('[data-weg]'); if (w) entfernen(w.closest('.anhang-chip').dataset.ref); });
        function auswahlHolen() {
            if (auswahl.length || box.dataset.lazy !== '1') { zeichnen(auswahl); return; }
            fetch(box.dataset.suche, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
                .then(function (r) { return r.json(); }).then(function (j) { auswahl = j.karten || []; zeichnen(auswahl); }).catch(function () { zeichnen([]); });
        }
        auf.addEventListener('click', function () { panel.hidden = !panel.hidden; if (!panel.hidden) { auswahlHolen(); suche.focus(); } });
        zu.addEventListener('click', function () { panel.hidden = true; });
        suche.addEventListener('input', function () {
            clearTimeout(timer);
            var q = suche.value.trim();
            if (q.length < 2) { zeichnen(auswahl); return; }
            timer = setTimeout(function () {
                fetch(box.dataset.suche + '?q=' + encodeURIComponent(q), { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
                    .then(function (r) { return r.json(); }).then(function (j) { if (suche.value.trim() === q) zeichnen(j.karten || []); }).catch(function () {});
            }, 250);
        });
        box.leeren = function () { gewaehlt.innerHTML = ''; gewaehlt.hidden = true; panel.hidden = true; suche.value = ''; };
    });
})();

/* Entwurf einer Frage im Browser merken, bis sie abgeschickt ist */
(function () {
    document.querySelectorAll('form[data-entwurf]').forEach(function (f) {
        var key = 'entwurf-' + f.dataset.entwurf;
        var felder = f.querySelectorAll('input[name="title"], textarea[name="body"]');
        try {
            var alt = JSON.parse(localStorage.getItem(key) || '{}');
            felder.forEach(function (el) { if (!el.value && alt[el.name]) el.value = alt[el.name]; });
            if (alt.title || alt.body) { var d = f.closest('details'); if (d) d.open = true; }
        } catch (e) {}
        f.addEventListener('input', function () {
            var w = {}; felder.forEach(function (el) { w[el.name] = el.value; });
            try { localStorage.setItem(key, JSON.stringify(w)); } catch (e) {}
        });
        f.addEventListener('submit', function () { try { localStorage.removeItem(key); } catch (e) {} });
    });
})();

/* ---------- Arbeitsbuch: Liste, zwei Spalten, Aufnahme, Mitnehmen ---------- */
(function () {
    var csrf = document.querySelector('meta[name="csrf-token"]');
    if (!csrf || !window.KURS) return;
    csrf = csrf.getAttribute('content');
    var timer = {};
    function speichern(box, id, wert) {
        var s = box.querySelector('[data-status]');
        clearTimeout(timer[id]);
        timer[id] = setTimeout(function () {
            if (s) s.textContent = 'speichert ...';
            fetch(window.KURS.antwort, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf }, body: JSON.stringify({ exercise_id: id, value: wert }) })
                .then(function (r) { if (!r.ok) throw 0; if (s) s.textContent = 'gespeichert'; })
                .catch(function () { if (s) s.textContent = 'nicht gespeichert, bitte nochmals'; });
        }, 900);
    }
    function wert(box) {
        var paare = box.hasAttribute('data-antwort-paare'), out = [];
        box.querySelectorAll('li').forEach(function (li) {
            var f = li.querySelectorAll('input');
            if (paare) { if (f[0].value.trim() || f[1].value.trim()) out.push([f[0].value.trim(), f[1].value.trim()]); }
            else if (f[0].value.trim()) out.push(f[0].value.trim());
        });
        return out;
    }
    function id(box) { return box.dataset.antwortListe || box.dataset.antwortPaare; }
    function neueZeile(box) {
        var ul = box.querySelector('ul'), li = ul.lastElementChild.cloneNode(true);
        li.querySelectorAll('input').forEach(function (i) { i.value = ''; });
        ul.appendChild(li);
        return li;
    }
    document.querySelectorAll('[data-antwort-liste], [data-antwort-paare]').forEach(function (box) {
        box.addEventListener('input', function (e) {
            var li = e.target.closest('li');
            if (li && li === box.querySelector('ul').lastElementChild && e.target.value.trim()) neueZeile(box);
            speichern(box, id(box), wert(box));
        });
        box.addEventListener('keydown', function (e) {
            if (e.key !== 'Enter' || e.target.tagName !== 'INPUT') return;
            e.preventDefault();
            var li = e.target.closest('li'), next = li.nextElementSibling || neueZeile(box);
            next.querySelector('input').focus();
        });
        box.addEventListener('click', function (e) {
            if (e.target.closest('[data-zeile-mehr]')) { neueZeile(box).querySelector('input').focus(); return; }
            var weg = e.target.closest('[data-zeile-weg]');
            if (weg) {
                var ul = box.querySelector('ul'), li = weg.closest('li');
                if (ul.children.length > 1) li.remove(); else li.querySelectorAll('input').forEach(function (i) { i.value = ''; });
                speichern(box, id(box), wert(box));
            }
        });
    });

    /* Aufnahme im Arbeitsbuch */
    document.querySelectorAll('[data-aufnahme-uebung]').forEach(function (box) {
        var start = box.querySelector('[data-ton-start]'), stopp = box.querySelector('[data-ton-stopp]'), zeit = box.querySelector('[data-ton-zeit]'), spieler = box.querySelector('[data-ton-spieler]');
        if (!navigator.mediaDevices || !window.MediaRecorder) { start.hidden = true; zeit.textContent = 'Dieser Browser kann nicht aufnehmen.'; return; }
        var rec = null, teile = [], t0 = 0, uhr = null;
        start.addEventListener('click', function () {
            navigator.mediaDevices.getUserMedia({ audio: true }).then(function (stream) {
                var typ = ['audio/mp4', 'audio/webm;codecs=opus', 'audio/webm'].find(function (t) { return MediaRecorder.isTypeSupported(t); }) || '';
                rec = new MediaRecorder(stream, typ ? { mimeType: typ } : {}); teile = []; t0 = Date.now();
                rec.ondataavailable = function (e) { if (e.data.size) teile.push(e.data); };
                rec.onstop = function () {
                    stream.getTracks().forEach(function (t) { t.stop(); }); clearInterval(uhr);
                    start.hidden = false; stopp.hidden = true; zeit.textContent = 'speichert ...';
                    var blob = new Blob(teile, { type: rec.mimeType || 'audio/webm' }), fd = new FormData();
                    fd.append('exercise_id', box.dataset.aufnahmeUebung);
                    fd.append('ton', blob, 'aufnahme.' + ((rec.mimeType || '').indexOf('mp4') >= 0 ? 'm4a' : 'webm'));
                    fetch(box.dataset.ziel, { method: 'POST', credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf }, body: fd })
                        .then(function (r) { return r.json(); })
                        .then(function (j) { spieler.src = j.url + '?t=' + Date.now(); spieler.hidden = false; zeit.textContent = 'gespeichert'; start.innerHTML = '<i class="fa-solid fa-microphone"></i>Nochmal aufnehmen'; })
                        .catch(function () { zeit.textContent = 'nicht gespeichert, bitte nochmals'; });
                };
                rec.start(); start.hidden = true; stopp.hidden = false;
                uhr = setInterval(function () { var s = Math.round((Date.now() - t0) / 1000); zeit.textContent = 'Aufnahme ' + Math.floor(s / 60) + ':' + ('0' + (s % 60)).slice(-2); }, 500);
            }).catch(function () { zeit.textContent = 'Kein Zugriff auf das Mikrofon.'; });
        });
        stopp.addEventListener('click', function () { if (rec && rec.state !== 'inactive') rec.stop(); });
    });

    /* Mitnehmen: nur diesen Teil drucken (oder als PDF sichern) */
    document.addEventListener('click', function (e) {
        var b = e.target.closest('[data-drucken]'); if (!b) return;
        var box = b.closest('[data-mitnehmen]');
        document.body.classList.add('drucke-mitnehmen'); box.classList.add('wird-gedruckt');
        window.print();
        setTimeout(function () { document.body.classList.remove('drucke-mitnehmen'); box.classList.remove('wird-gedruckt'); }, 500);
    });

    /* Lebensrad zeichnet sich neu, wenn eine Skala angeklickt wird */
    var rad = document.querySelector('[data-lebensrad] [data-rad-flaeche]');
    if (rad) {
        document.addEventListener('click', function (e) {
            if (!e.target.closest('[data-antwort-skala]')) return;
            setTimeout(function () {
                var skalen = document.querySelectorAll('[data-antwort-skala]'), n = Math.max(3, skalen.length), pts = [];
                skalen.forEach(function (s, i) {
                    var an = s.querySelector('[data-wert].bg-primary'), w = an ? parseInt(an.dataset.wert, 10) : 0, a = -Math.PI / 2 + 2 * Math.PI * i / n;
                    pts.push((100 + Math.cos(a) * 8 * w).toFixed(1) + ',' + (100 + Math.sin(a) * 8 * w).toFixed(1));
                });
                rad.setAttribute('points', pts.join(' '));
            }, 50);
        });
    }
})();


/* ---------- Reverb: offene Gespraeche erfahren sofort von neuen Nachrichten (Pusher-Protokoll, ohne Bibliothek) ---------- */
(function () {
    var meta = document.querySelector('meta[name="reverb"]');
    var verlauf = document.querySelector('[data-verlauf][data-kanal]');
    if (!meta || !verlauf || !window.WebSocket) return;
    var cfg; try { cfg = JSON.parse(meta.content); } catch (e) { return; }
    var kanal = 'private-' + verlauf.dataset.kanal;
    var csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    var versuch = 0, ws;

    function verbinden() {
        var port = cfg.port && cfg.port !== 443 && cfg.port !== 80 ? ':' + cfg.port : '';
        ws = new WebSocket((cfg.scheme === 'https' ? 'wss' : 'ws') + '://' + cfg.host + port + '/app/' + cfg.key + '?protocol=7&client=js&version=1.0&flash=false');
        ws.onopen = function () { versuch = 0; };
        ws.onmessage = function (e) {
            var m; try { m = JSON.parse(e.data); } catch (x) { return; }
            if (m.event === 'pusher:connection_established') {
                var d = typeof m.data === 'string' ? JSON.parse(m.data) : m.data;
                fetch('/broadcasting/auth', { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf }, body: JSON.stringify({ socket_id: d.socket_id, channel_name: kanal }) })
                    .then(function (r) { return r.ok ? r.json() : null; })
                    .then(function (a) { if (a && a.auth) ws.send(JSON.stringify({ event: 'pusher:subscribe', data: { channel: kanal, auth: a.auth } })); })
                    .catch(function () {});
            } else if (m.event === 'pusher_internal:subscription_succeeded') {
                if (window.gespraechTakt) window.gespraechTakt(20000);
            } else if (m.event === 'pusher:ping') {
                ws.send(JSON.stringify({ event: 'pusher:pong', data: {} }));
            } else if (m.event === 'nachricht' && window.gespraechNachfragen) {
                window.gespraechNachfragen();
            }
        };
        ws.onclose = function () {
            if (window.gespraechTakt) window.gespraechTakt(5000);
            setTimeout(verbinden, Math.min(30000, 1000 * Math.pow(2, versuch++)));
        };
        ws.onerror = function () { try { ws.close(); } catch (x) {} };
    }
    verbinden();
    document.addEventListener('visibilitychange', function () { if (!document.hidden && ws && ws.readyState === WebSocket.CLOSED) verbinden(); });
})();

/* ---------- Nachschlagen: ein Feld fuer alles, Vorschau im Fenster, Teilen, Sammlungen ---------- */
(function () {
    var form = document.querySelector('form[data-nachschlagen]');
    var csrf = (document.querySelector('meta[name="csrf-token"]') || {}).getAttribute ? document.querySelector('meta[name="csrf-token"]').getAttribute('content') : '';
    if (form) {
        var ziel = document.querySelector(form.dataset.ziel), los = form.querySelector('[data-los]'), feld = form.querySelector('textarea'), thema = form.querySelector('[data-thema]');
        function holen(mitThema) {
            var d = new URLSearchParams();
            if (mitThema && thema && thema.value) { d.set('thema', thema.value); feld.value = ''; } else { d.set('q', feld.value.trim()); if (thema) thema.value = ''; }
            if (!d.get('q') && !d.get('thema')) { feld.focus(); return; }
            var alt = los.textContent; los.disabled = true; los.textContent = 'Ich schaue nach …';
            ziel.innerHTML = '';
            var url = form.action + '?' + d.toString();
            fetch(url, { credentials: 'same-origin', headers: { 'X-Requested-With': 'fetch' } })
                .then(function (r) { return r.text(); })
                .then(function (html) { ziel.innerHTML = html; history.replaceState(null, '', url); ziel.scrollIntoView({ behavior: 'smooth', block: 'nearest' }); })
                .catch(function () { ziel.innerHTML = '<p class="fu-antwort">Das hat nicht geklappt. Probier es gleich noch einmal.</p>'; })
                .then(function () { los.disabled = false; los.textContent = alt; });
        }
        form.addEventListener('submit', function (e) { e.preventDefault(); holen(false); });
        feld.addEventListener('keydown', function (e) { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); holen(false); } });
        if (thema) thema.addEventListener('change', function () { if (thema.value) holen(true); });
    }

    /* Vorschau im Fenster (Karte antippen oder Teilen-Zeichen) */
    var basis = document.querySelector('[data-fu-vorschau-url]');
    var vorschauUrl = basis ? basis.dataset.fuVorschauUrl : '/nachschlagen/vorschau';
    function vorschau(key, teilen) {
        if (!window.appSheet) return;
        var teile = key.split('-');
        window.appSheet.auf('<p class="hinweis">Einen Moment …</p>');
        fetch(vorschauUrl + '/' + teile[0] + '/' + teile[1], { credentials: 'same-origin', headers: { 'X-Requested-With': 'fetch' } })
            .then(function (r) { return r.ok ? r.text() : Promise.reject(); })
            .then(function (html) {
                window.appSheet.auf(html);
                var s = document.querySelector('[data-fu-schicken]');
                if (s) s.classList.toggle('an', !!teilen);
            })
            .catch(function () { window.appSheet.auf('<p>Das konnte nicht geladen werden.</p>'); });
    }
    document.addEventListener('click', function (e) {
        var t = e.target.closest('[data-fu-teilen]');
        if (t) { e.preventDefault(); e.stopPropagation(); vorschau(t.dataset.fuTeilen, true); return; }
        if (e.target.closest('form[data-merken]') || e.target.closest('.fu-wahl') || e.target.closest('.fu-tuer a')) return;
        var k = e.target.closest('[data-fu-karte]');
        if (k && !e.target.closest('a')) { vorschau(k.dataset.fuKarte, false); }
    });
    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Enter') return;
        var k = e.target.closest && e.target.closest('[data-fu-karte]');
        if (k && e.target === k) vorschau(k.dataset.fuKarte, false);
    });

    /* Im Fenster: Link teilen (Handy) oder kopieren, in den Chat schicken */
    document.addEventListener('click', function (e) {
        var v = e.target.closest('[data-fu-vorschau]'); if (!v) return;
        var stand = v.querySelector('[data-fu-stand]');
        if (e.target.closest('[data-fu-link]')) {
            var link = v.dataset.link, titel = v.dataset.titel;
            if (!link) return;
            if (navigator.share) { navigator.share({ title: titel, url: link }).catch(function () {}); }
            else if (navigator.clipboard) { navigator.clipboard.writeText(link).then(function () { if (stand) stand.textContent = 'Link kopiert.'; }); }
            else { window.prompt('Link kopieren:', link); }
        }
        var s = e.target.closest('[data-fu-senden]');
        if (s) {
            var an = [].map.call(v.querySelectorAll('[data-fu-an]:checked'), function (x) { return x.value; });
            if (!an.length) { if (stand) stand.textContent = 'Wähl aus, wer es bekommen soll.'; return; }
            if (stand) stand.textContent = 'Wird geschickt …';
            var d = new FormData();
            d.append('items[]', v.dataset.fuVorschau);
            d.append('gruss', (v.querySelector('[data-fu-gruss]') || {}).value || '');
            an.forEach(function (x) { d.append('an[]', x); });
            fetch(s.dataset.fuSenden, { method: 'POST', credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf }, body: d })
                .then(function (r) { return r.json().then(function (j) { return r.ok ? j : Promise.reject(j); }); })
                .then(function (j) { if (stand) stand.textContent = 'An ' + j.an + ' geschickt.'; v.querySelectorAll('[data-fu-an]:checked').forEach(function (x) { x.checked = false; }); })
                .catch(function (j) { if (stand) stand.textContent = (j && j.msg) || 'Das hat nicht geklappt.'; });
        }
    });

    /* Leiste fuer Sammlungen (Coachin) */
    var leiste = document.querySelector('[data-fu-leiste]');
    if (!leiste) return;
    function gewaehlt() { return [].map.call(document.querySelectorAll('[data-fu-wahl]:checked'), function (x) { return x.value; }); }
    function auffrischen() {
        var g = gewaehlt();
        leiste.querySelector('[data-fu-zahl]').textContent = g.length;
        leiste.classList.toggle('offen', g.length > 0);
        leiste.setAttribute('aria-hidden', g.length ? 'false' : 'true');
        document.querySelectorAll('[data-fu-wahl]').forEach(function (x) { var k = x.closest('[data-fu-karte]'); if (k) k.classList.toggle('gewaehlt', x.checked); });
    }
    document.addEventListener('change', function (e) { if (e.target.matches('[data-fu-wahl]')) auffrischen(); });
    leiste.querySelector('[data-fu-leeren]').addEventListener('click', function () { document.querySelectorAll('[data-fu-wahl]:checked').forEach(function (x) { x.checked = false; }); auffrischen(); });
    function schicken(nurLink) {
        var g = gewaehlt(); if (!g.length) return;
        var st = leiste.querySelector('[data-fu-leiste-stand]'); st.textContent = 'Wird gemacht …';
        var d = new FormData();
        g.forEach(function (x) { d.append('items[]', x); });
        d.append('name', leiste.querySelector('[data-fu-name]').value);
        d.append('gruss', leiste.querySelector('[data-fu-leiste-gruss]').value);
        if (!nurLink) [].forEach.call(leiste.querySelectorAll('[data-fu-leiste-an]:checked'), function (x) { d.append('an[]', x.value); });
        fetch(leiste.dataset.senden, { method: 'POST', credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf }, body: d })
            .then(function (r) { return r.json().then(function (j) { return r.ok ? j : Promise.reject(j); }); })
            .then(function (j) {
                st.innerHTML = (j.an ? 'An ' + j.an + ' Person' + (j.an > 1 ? 'en' : '') + ' geschickt. ' : '') + '<a href="' + j.link + '" target="_blank" rel="noopener">Link öffnen</a> · <button type="button" class="knopf-text" data-kopieren="' + j.link + '">Link kopieren</button>';
            })
            .catch(function (j) { st.textContent = (j && j.msg) || 'Das hat nicht geklappt.'; });
    }
    leiste.querySelector('[data-fu-leiste-senden]').addEventListener('click', function () { schicken(false); });
    leiste.querySelector('[data-fu-leiste-link]').addEventListener('click', function () { schicken(true); });
})();

/* ---------- Coachees: Auskunft ("Frag mich etwas zu deinem Betrieb") ---------- */
(function () {
    var box = document.querySelector('[data-auskunft]'); if (!box) return;
    var form = box.querySelector('[data-auskunft-form]'), feld = form.querySelector('input[name=frage]'), ziel = box.querySelector('[data-auskunft-antwort]'), los = form.querySelector('[data-auskunft-los]');
    var csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    function fragen() {
        var q = feld.value.trim(); if (q.length < 3) { ziel.innerHTML = '<p class="fu-antwort">Stell mir eine ganze Frage.</p>'; return; }
        ziel.innerHTML = '<p class="fu-antwort">Ich schaue nach …</p>'; los.disabled = true;
        var d = new FormData(); d.append('frage', q);
        fetch(box.dataset.url, { method: 'POST', credentials: 'same-origin', headers: { 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'fetch' }, body: d })
            .then(function (r) { return r.text(); }).then(function (html) { ziel.innerHTML = html; })
            .catch(function () { ziel.innerHTML = '<p class="fu-antwort">Das hat nicht geklappt.</p>'; })
            .then(function () { los.disabled = false; });
    }
    form.addEventListener('submit', function (e) { e.preventDefault(); fragen(); });
    box.querySelectorAll('[data-auskunft-beispiel]').forEach(function (b) { b.addEventListener('click', function () { feld.value = b.textContent; fragen(); }); });
})();

/* ---------- Assistent: Beispiel tippen, dann steht es im Feld und geht ab ---------- */
(function () {
    document.querySelectorAll('[data-dialog-beispiel]').forEach(function (b) {
        b.addEventListener('click', function () {
            var form = b.closest('section').querySelector('textarea[name=text]');
            if (!form) return;
            form.value = b.textContent.trim();
            form.focus();
            if (!form.form.querySelector('button[type=submit]').disabled) form.form.requestSubmit();
        });
    });
})();

// Ein Link mit data-aufklappen="id" oeffnet das <details> mit dieser id und springt hin
(function () {
    document.querySelectorAll('[data-aufklappen]').forEach(function (a) {
        a.addEventListener('click', function (e) {
            var d = document.getElementById(a.getAttribute('data-aufklappen'));
            if (!d) return;
            e.preventDefault();
            d.open = true;
            d.scrollIntoView({ behavior: 'smooth', block: 'start' });
            var f = d.querySelector('input, select, textarea');
            if (f) setTimeout(function () { f.focus(); }, 300);
        });
    });
    if (location.hash) {
        var d = document.querySelector(location.hash);
        if (d && d.tagName === 'DETAILS') d.open = true;
    }
})();

/* ---------- Fragen: Weiterlesen, Antwort auf Antwort, @-Erwaehnungen, neue Antworten nachladen ---------- */
(function () {
    document.addEventListener('click', function (e) {
        var k = e.target.closest('[data-weiterlesen-knopf]');
        if (!k) return;
        var t = k.previousElementSibling;
        if (!t || t.dataset.weiterlesen === undefined) return;
        var offen = t.classList.toggle('offen');
        k.textContent = offen ? 'Weniger' : 'Weiterlesen';
    });

    /* Antworten auf eine Antwort: das eine Formular unten bekommt parent_id und springt hoch */
    var form = document.getElementById('antworten');
    if (form) {
        var parent = form.querySelector('[data-parent]'), hinweis = form.querySelector('[data-antwort-auf-hinweis]'), name = form.querySelector('[data-antwort-auf-name]');
        document.addEventListener('click', function (e) {
            var b = e.target.closest('[data-antwort-auf]');
            if (b) {
                parent.value = b.dataset.antwortAuf; name.textContent = b.dataset.name || ''; hinweis.hidden = false;
                form.scrollIntoView({ behavior: 'smooth', block: 'center' });
                var t = form.querySelector('textarea'); if (t) { t.focus(); if (b.dataset.name && !t.value) t.value = '@' + b.dataset.name.replace(/\s+/g, '-') + ' '; }
            }
            if (e.target.closest('[data-antwort-auf-weg]')) { parent.value = ''; hinweis.hidden = true; }
        });
    }

    /* @-Erwaehnungen: beim Tippen von @ Namen vorschlagen */
    document.querySelectorAll('textarea[data-erwaehnen]').forEach(function (t) {
        var namen = [];
        try { namen = JSON.parse(t.dataset.erwaehnen || '[]'); } catch (e) {}
        if (!namen.length) return;
        var liste = null, wahl = 0, treffer = [];
        function zu() { if (liste) { liste.remove(); liste = null; } }
        function wort() {
            var bis = t.selectionStart, vor = t.value.slice(0, bis), m = vor.match(/(?:^|[\s(])@([\p{L}\-]*)$/u);
            return m ? { start: bis - m[1].length - 1, text: m[1] } : null;
        }
        function einsetzen(n) {
            var w = wort(); if (!w) return;
            var voll = '@' + n.replace(/\s+/g, '-') + ' ';
            t.value = t.value.slice(0, w.start) + voll + t.value.slice(t.selectionStart);
            var pos = w.start + voll.length; t.setSelectionRange(pos, pos); t.focus(); zu();
            t.dispatchEvent(new Event('input', { bubbles: true }));
        }
        function zeichnen() {
            var w = wort();
            if (!w) return zu();
            var such = w.text.toLowerCase();
            treffer = namen.filter(function (n) { return n.toLowerCase().indexOf(such) === 0 || n.toLowerCase().split(' ')[0].indexOf(such) === 0; }).slice(0, 6);
            if (!treffer.length) return zu();
            if (!liste) { liste = document.createElement('div'); liste.className = 'erwaehnen-liste'; t.parentNode.style.position = 'relative'; t.parentNode.appendChild(liste); }
            wahl = Math.min(wahl, treffer.length - 1);
            liste.innerHTML = '';
            treffer.forEach(function (n, i) {
                var b = document.createElement('button'); b.type = 'button'; b.textContent = n; if (i === wahl) b.classList.add('an');
                b.addEventListener('mousedown', function (e) { e.preventDefault(); einsetzen(n); });
                liste.appendChild(b);
            });
            liste.style.left = '12px'; liste.style.top = (t.offsetTop + t.offsetHeight - 6) + 'px';
        }
        t.addEventListener('input', function () { wahl = 0; zeichnen(); });
        t.addEventListener('keydown', function (e) {
            if (!liste) return;
            if (e.key === 'ArrowDown') { e.preventDefault(); wahl = (wahl + 1) % treffer.length; zeichnen(); }
            else if (e.key === 'ArrowUp') { e.preventDefault(); wahl = (wahl - 1 + treffer.length) % treffer.length; zeichnen(); }
            else if (e.key === 'Enter' || e.key === 'Tab') { e.preventDefault(); einsetzen(treffer[wahl]); }
            else if (e.key === 'Escape') { zu(); }
        });
        t.addEventListener('blur', function () { setTimeout(zu, 150); });
    });

    /* Neue Antworten nachladen: alle 20 Sekunden nachfragen, Knopf "2 neue Antworten anzeigen" */
    var seite = document.querySelector('[data-frage-neu]');
    if (seite) {
        var knopf = seite.querySelector('[data-neue-antworten]'), box = seite.querySelector('[data-antworten]');
        var letzte = parseInt(seite.dataset.letzte || '0', 10), wartend = null;
        function nachfragen() {
            if (document.hidden) return;
            fetch(seite.dataset.frageNeu + '?seit=' + letzte, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
                .then(function (r) { return r.json(); })
                .then(function (j) {
                    if (!j.anzahl) return;
                    wartend = j; letzte = j.letzte;
                    knopf.textContent = j.anzahl === 1 ? 'Eine neue Antwort anzeigen' : j.anzahl + ' neue Antworten anzeigen';
                    knopf.hidden = false;
                })
                .catch(function () {});
        }
        knopf.addEventListener('click', function () {
            if (!wartend) return;
            var t = document.createElement('div'); t.innerHTML = wartend.html;
            while (t.firstChild) box.appendChild(t.firstChild);
            wartend = null; knopf.hidden = true;
            var leer = seite.querySelector('.leer'); if (leer) leer.remove();
        });
        setInterval(nachfragen, 20000);
        document.addEventListener('visibilitychange', function () { if (!document.hidden) nachfragen(); });
    }
})();

/* ---------- Profilfoto: im Browser auf 1200 px verkleinern, dann hochladen (Handyfotos sind zu gross) ---------- */
(function () {
    var form = document.querySelector('form[data-foto]');
    var input = form && form.querySelector('[data-foto-datei]');
    if (!form || !input) return;
    var status = form.querySelector('[data-foto-status]');
    var csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    function melden(t) { if (status) status.textContent = t; }
    function hochladen(blob, name) {
        var fd = new FormData(); fd.append('foto', blob, name); fd.append('_token', csrf);
        return fetch(form.action, { method: 'POST', credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf }, body: fd })
            .then(function (r) { return r.json().catch(function () { return {}; }).then(function (j) { if (!r.ok) throw new Error(j.fehler || (j.errors && j.errors.foto && j.errors.foto[0]) || ('Fehler ' + r.status)); return j; }); });
    }
    input.addEventListener('change', function () {
        var f = input.files[0];
        if (!f) return;
        melden('Foto wird vorbereitet ...');
        var url = URL.createObjectURL(f), img = new Image();
        img.onload = function () {
            URL.revokeObjectURL(url);
            var max = 1200, s = Math.min(1, max / Math.max(img.width, img.height));
            var c = document.createElement('canvas'); c.width = Math.round(img.width * s); c.height = Math.round(img.height * s);
            c.getContext('2d').drawImage(img, 0, 0, c.width, c.height);
            c.toBlob(function (blob) {
                if (!blob) { form.submit(); return; }
                melden('Foto wird hochgeladen ...');
                hochladen(blob, 'foto.jpg').then(function () { window.location.href = form.action.replace(/\/foto$/, '') + '#foto'; window.location.reload(); })
                    .catch(function (e) { melden('Das hat nicht geklappt: ' + e.message); });
            }, 'image/jpeg', 0.86);
        };
        img.onerror = function () { URL.revokeObjectURL(url); form.submit(); };   // z. B. HEIC ohne Browser-Unterstuetzung: der Server versucht es
        img.src = url;
    });
})();

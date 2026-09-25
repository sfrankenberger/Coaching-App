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
    window.gespraechNachfragen = nachfragen;
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

/* ---------- Push-Nachrichten einschalten ---------- */
(function () {
    var box = document.querySelector('[data-push]');
    if (!box) return;
    var an = box.querySelector('[data-push-an]'), aus = box.querySelector('[data-push-aus]'), status = box.querySelector('[data-push-status]');
    var csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
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
    var liste = document.querySelector('[data-kapitel]');
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
        var key = box.dataset.medien, start = parseInt(box.dataset.start || '0', 10), zuletzt = 0;
        function melden(s, d, sofort) {
            document.dispatchEvent(new CustomEvent('medien:zeit', { detail: { key: key, sekunden: s } }));
            if (!sofort && Math.abs(s - zuletzt) < 10) return;
            zuletzt = s; senden(key, s, d);
        }
        var v = box.querySelector('video, audio');
        if (v) {
            v.addEventListener('loadedmetadata', function () { if (start > 5 && start < v.duration - 10) v.currentTime = start; });
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
                if (start > 5 && !gesprungen) { gesprungen = true; an({ method: 'setCurrentTime', value: start }); }
            }
            if (d.event === 'timeupdate' && d.data) { dauer = d.data.duration || dauer; melden(d.data.seconds, dauer, false); }
            if (d.event === 'pause' && d.data) melden(d.data.seconds, dauer, true);
            if (d.event === 'ended') melden(dauer, dauer, true);
        });
        /* Falls "ready" schon vorbei ist, bevor wir lauschen: nach dem Laden direkt anmelden */
        f.addEventListener('load', function () {
            ['timeupdate', 'pause', 'ended'].forEach(function (ev) { an({ method: 'addEventListener', value: ev }); });
            if (start > 5 && !gesprungen) { gesprungen = true; setTimeout(function () { an({ method: 'setCurrentTime', value: start }); }, 600); }
        });
    }
    boxen.forEach(beobachten);
})();

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
            rec = new SR(); rec.lang = sprache; rec.interimResults = false; rec.continuous = true;
            rec.onresult = function (e) {
                var neu = '';
                for (var i = e.resultIndex; i < e.results.length; i++) if (e.results[i].isFinal) neu += e.results[i][0].transcript;
                if (!neu) return;
                var vor = t.value && !/\s$/.test(t.value) ? ' ' : '';
                t.value += vor + neu.trim();
                t.dispatchEvent(new Event('input', { bubbles: true }));
            };
            rec.onend = function () { rec = null; b.classList.remove('an'); t.dispatchEvent(new Event('focusout', { bubbles: true })); };
            rec.onerror = function () { rec = null; b.classList.remove('an'); };
            rec.start(); b.classList.add('an');
        });
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

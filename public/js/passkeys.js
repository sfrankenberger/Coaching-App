/* Passkeys (WebAuthn): anlegen im Profil, anmelden auf /anmelden. Ohne Build. */
(function () {
    if (!window.PublicKeyCredential) { document.querySelectorAll('[data-passkey]').forEach(function (b) { b.hidden = true; }); return; }
    var csrf = document.querySelector('meta[name="csrf-token"]');
    csrf = csrf ? csrf.getAttribute('content') : '';

    function b64ToBuf(s) { s = s.replace(/-/g, '+').replace(/_/g, '/'); s += '='.repeat((4 - s.length % 4) % 4); var raw = atob(s); var a = new Uint8Array(raw.length); for (var i = 0; i < raw.length; i++) a[i] = raw.charCodeAt(i); return a.buffer; }
    function bufToB64(b) { var s = ''; var a = new Uint8Array(b); for (var i = 0; i < a.length; i++) s += String.fromCharCode(a[i]); return btoa(s).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, ''); }
    function post(url, data) {
        return fetch(url, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf }, body: JSON.stringify(data || {}) })
            .then(function (r) { return r.json().then(function (j) { if (!r.ok) throw new Error(j.fehler || j.message || ('Fehler ' + r.status)); return j; }); });
    }
    function status(el, text) { var s = el.closest('[data-passkey-box]')?.querySelector('[data-passkey-status]'); if (s) s.textContent = text; }

    // Anlegen
    document.addEventListener('click', function (e) {
        var b = e.target.closest('[data-passkey="anlegen"]'); if (!b) return;
        b.disabled = true; status(b, 'Einen Moment ...');
        post(b.dataset.optionen).then(function (opt) {
            opt.challenge = b64ToBuf(opt.challenge);
            opt.user.id = b64ToBuf(opt.user.id);
            (opt.excludeCredentials || []).forEach(function (c) { c.id = b64ToBuf(c.id); });
            return navigator.credentials.create({ publicKey: opt });
        }).then(function (cred) {
            var alias = b.closest('[data-passkey-box]')?.querySelector('[name="alias"]');
            return post(b.dataset.speichern, {
                id: cred.id, rawId: bufToB64(cred.rawId), type: cred.type,
                response: { clientDataJSON: bufToB64(cred.response.clientDataJSON), attestationObject: bufToB64(cred.response.attestationObject) },
                alias: alias ? alias.value : ''
            });
        }).then(function () { status(b, 'Passkey gespeichert.'); window.location.reload(); })
          .catch(function (err) { b.disabled = false; status(b, err.name === 'NotAllowedError' ? 'Abgebrochen.' : ('Das hat nicht geklappt: ' + err.message)); });
    });

    // Anmelden
    document.addEventListener('click', function (e) {
        var b = e.target.closest('[data-passkey="anmelden"]'); if (!b) return;
        b.disabled = true; status(b, 'Einen Moment ...');
        var email = document.getElementById('email');
        post(b.dataset.optionen, { email: email && email.value ? email.value : null }).then(function (opt) {
            opt.challenge = b64ToBuf(opt.challenge);
            (opt.allowCredentials || []).forEach(function (c) { c.id = b64ToBuf(c.id); });
            return navigator.credentials.get({ publicKey: opt });
        }).then(function (cred) {
            return post(b.dataset.anmelden, {
                id: cred.id, rawId: bufToB64(cred.rawId), type: cred.type,
                response: { clientDataJSON: bufToB64(cred.response.clientDataJSON), authenticatorData: bufToB64(cred.response.authenticatorData), signature: bufToB64(cred.response.signature), userHandle: cred.response.userHandle ? bufToB64(cred.response.userHandle) : null },
                weiter: b.dataset.weiter || '', remember: true
            });
        }).then(function (j) { window.location.href = j.weiter || '/'; })
          .catch(function (err) { b.disabled = false; status(b, err.name === 'NotAllowedError' ? 'Abgebrochen.' : (err.message || 'Das hat nicht geklappt.')); });
    });
})();

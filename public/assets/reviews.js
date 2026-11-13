/** L’anteprima usa il file locale e lascia al server il controllo definitivo del formato e della durata. */
(() => {
    const form = document.querySelector('[data-audition-form]');
    if (!form) return;
    const submit = form.querySelector('button[type=submit]');
    const initiallyDisabled = submit.disabled;
    const input = form.querySelector('[data-audition-video]');
    const preview = form.querySelector('[data-video-local-preview]');
    const feedback = form.querySelector('[data-video-selection]');
    const role = form.querySelector('[data-audition-role]');
    const ensemble = form.querySelector('[data-ensemble-fields]');
    const guides = form.querySelectorAll('[data-role-guide]');
    const catalog = form.querySelector('[data-role-catalog]');
    let url;

    function syncRole() {
        ensemble.hidden = role.value !== 'ensemble';
        ensemble.querySelectorAll('input,textarea').forEach(field => { field.disabled = ensemble.hidden; });
        catalog.open = Boolean(role.value && role.value !== 'ensemble');
        guides.forEach(guide => { guide.open = guide.dataset.roleGuide === role.value; });
    }
    syncRole();
    role.addEventListener('change', syncRole);

    input.addEventListener('change', () => {
        if (url) URL.revokeObjectURL(url);
        url = undefined;
        preview.pause();
        preview.removeAttribute('src');
        preview.load();
        preview.hidden = true;
        input.setCustomValidity('');
        feedback.textContent = '';
        const file = input.files[0];
        if (!file) return;
        if (file.size > 100_000_000) {
            input.setCustomValidity('Il self-tape deve pesare al massimo 100 MB.');
        } else if (!/\.(mp4|mov|webm)$/i.test(file.name)) {
            input.setCustomValidity('Scegli un video MP4, MOV o WebM.');
        }
        if (!input.reportValidity()) return;
        const size = (file.size / 1_000_000).toLocaleString('it-IT', { maximumFractionDigits: 1 });
        feedback.textContent = file.name + ' · ' + size + ' MB';
        url = URL.createObjectURL(file);
        preview.src = url;
    });
    preview.addEventListener('loadedmetadata', () => {
        if (!url || preview.currentSrc !== url) return;
        preview.hidden = false;
        if (preview.duration > 300) {
            input.setCustomValidity('Il self-tape deve durare al massimo 5 minuti.');
            input.reportValidity();
        }
    });
    preview.addEventListener('error', () => {
        if (url && preview.currentSrc === url) feedback.textContent += ' · Anteprima non disponibile in questo browser.';
    });
    form.querySelectorAll('[data-max-file-bytes]').forEach(field => {
        field.addEventListener('change', () => {
            const limit = Number(field.dataset.maxFileBytes);
            field.setCustomValidity(field.files[0]?.size > limit ? 'Il file deve pesare al massimo ' + (limit / 1048576) + ' MB.' : '');
            field.reportValidity();
        });
    });
    form.addEventListener('submit', () => {
        submit.disabled = true;
        form.querySelector('[data-audition-status]').textContent = 'Invio degli allegati in corso. Attendi la conferma senza chiudere la pagina.';
    });
    addEventListener('pageshow', () => { submit.disabled = initiallyDisabled; });
})();
/** Il nuovo codice viene richiesto con protezione CSRF e non modifica i dati o gli allegati già scelti. */
(() => {
    const captcha = document.querySelector('[data-local-captcha]');
    if (!captcha) return;
    const button = captcha.querySelector('[data-captcha-renew]'), status = captcha.querySelector('[data-captcha-status]');
    const id = captcha.querySelector('[data-captcha-id]');
    button.addEventListener('click', async () => {
        button.disabled = true;
        try {
            const response = await fetch(captcha.dataset.renew, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                },
                body: JSON.stringify({ captcha_id: id.value }),
            });
            if (!response.ok) throw new Error('captcha');
            const data = await response.json();
            id.value = data.id;
            captcha.querySelector('[data-captcha-image]').src = data.image;
            captcha.querySelector('[data-captcha-code]').value = ''; status.textContent = 'Codice aggiornato.';
        } catch { status.textContent = 'Impossibile aggiornare il codice. Attendi qualche istante e riprova.'; }
        finally { button.disabled = false; }
    });
})();
/** Ogni scheda riproduce un video alla volta senza avviare gli altri file in sottofondo. */
(() => {
    const choices = document.querySelector('[data-video-choices]');
    if (!choices) return;
    const cards = [...document.querySelectorAll('[data-review-video]')];
    function select(id) {
        cards.forEach(card => { const active = card.dataset.reviewVideo === id; card.hidden = !active; if (!active) card.querySelector('video')?.pause(); });
        choices.querySelectorAll('button').forEach(button => button.setAttribute('aria-pressed', String(button.dataset.videoChoice === id)));
    }
    choices.hidden = false;
    choices.querySelectorAll('button').forEach(button => button.addEventListener('click', () => select(button.dataset.videoChoice)));
    select(choices.querySelector('button').dataset.videoChoice);
})();
/** I messaggi ricevuti sono inseriti come testo; l'autenticazione e l'autore restano verificati dal server. */
(() => {
    const chat = document.querySelector('[data-review-chat]');
    if (!chat || chat.dataset.live !== 'true') return;
    const form = chat.querySelector('[data-chat-form]');
    const list = chat.querySelector('[data-chat-messages]');
    const status = chat.querySelector('[data-chat-status]');
    let after = Number(chat.dataset.after), stopped = false, pending;
    function add(message) {
        if (list.querySelector('[data-comment-id="' + Number(message.id) + '"]')) return;
        chat.querySelector('[data-chat-empty]')?.remove();
        const nearEnd = list.scrollHeight - list.scrollTop - list.clientHeight < 90;
        const item = document.createElement('article'); item.className = 'chat-message' + (message.own ? ' own' : ''); item.dataset.commentId = message.id;
        const header = document.createElement('header'), author = document.createElement('strong'), time = document.createElement('time'), body = document.createElement('p');
        author.textContent = message.author; time.textContent = message.time; body.textContent = message.body;
        header.append(author, time); item.append(header, body);
        const later = [...list.querySelectorAll('[data-comment-id]')].find(element => Number(element.dataset.commentId) > message.id);
        list.insertBefore(item, later || null);
        if (nearEnd || message.own) list.scrollTop = list.scrollHeight;
    }
    async function sync() {
        if (pending) return pending;
        pending = (async () => {
            const url = new URL(chat.dataset.url, location.origin); url.searchParams.set('after', after);
            const response = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
            if (response.redirected || [401,403].includes(response.status)) { stopped = true; return; }
            if (!response.ok) return;
            const data = await response.json();
            data.comments.forEach(message => { add(message); after = Math.max(after, message.id); });
        })().finally(() => { pending = null; });
        return pending;
    }
    async function poll() { if (stopped) return; if (!document.hidden) { try { await sync(); } catch {} } setTimeout(poll, 15000); }
    setTimeout(poll, 15000);
    list.scrollTop = list.scrollHeight;
    form.addEventListener('submit', async event => {
        event.preventDefault();
        const button = form.querySelector('button'); button.disabled = true; status.textContent = 'Invio in corso…';
        try {
            const response = await fetch(form.action, { method: 'POST', body: new FormData(form), headers: { Accept: 'application/json' }, credentials: 'same-origin' });
            if (!response.ok) { status.textContent = 'Invio non riuscito. Verifica il testo e la sessione di accesso.'; return; }
            const data = await response.json(); add(data.comment); form.querySelector('textarea').value = ''; status.textContent = 'Commento inviato.';
            try { await sync(); } catch {}
        } catch { status.textContent = 'Connessione non disponibile. Il testo è conservato nel campo: verifica i commenti prima di riprovare.'; }
        finally { button.disabled = false; }
    });
})();

/** L'ingresso si può saltare sempre; il ritorno nella stessa scheda apre direttamente il progetto. */
(() => {
    const entry = document.querySelector('[data-entry-film]');
    const film = document.querySelector('[data-entry-video]');
    const shell = document.getElementById('site-shell');
    const reduce = window.matchMedia('(prefers-reduced-motion: reduce)');
    if (!entry || !film || !shell) return;
    const toggle = entry.querySelector('[data-entry-toggle]');
    const audio = entry.querySelector('[data-entry-audio]');
    const enter = entry.querySelector('[data-enter-landing]');
    const fallback = entry.querySelector('[data-entry-fallback]');
    const root = document.documentElement;
    const key = 'nerone_ingresso';
    let seen = false;
    let opening = false;
    try { seen = sessionStorage.getItem(key) === 'v2'; } catch {}
    const force = new URLSearchParams(location.search).get('ingresso') === '1';
    const bypass = reduce.matches || navigator.connection?.saveData || (!force && (seen || location.hash));
    function update() {
        toggle.textContent = film.paused ? 'Riproduci' : 'Pausa';
        toggle.setAttribute('aria-label', film.paused ? 'Riproduci il trailer' : 'Metti in pausa il trailer');
        audio.textContent = film.muted ? 'Attiva audio' : 'Disattiva audio';
        audio.setAttribute('aria-pressed', String(!film.muted));
    }
    function reveal() {
        if (opening || entry.hidden) return;
        opening = true;
        film.pause();
        try { sessionStorage.setItem(key, 'v2'); } catch {}
        shell.inert = false;
        shell.removeAttribute('aria-hidden');
        root.classList.add('film-opening');
        function finish() {
            entry.hidden = true;
            root.classList.remove('film-start', 'film-opening');
            window.scrollTo({top: 0, behavior: 'instant'});
            document.getElementById('project-title')?.focus({preventScroll: true});
        }
        if (reduce.matches) finish(); else window.setTimeout(finish, 850);
    }
    if (bypass) { entry.hidden = true; return; }
    root.classList.add('film-start');
    shell.inert = true;
    shell.setAttribute('aria-hidden', 'true');
    entry.setAttribute('role', 'dialog');
    entry.setAttribute('aria-modal', 'true');
    entry.setAttribute('aria-labelledby', 'entry-title');
    film.controls = false;
    film.muted = true;
    entry.focus({preventScroll: true});
    toggle.addEventListener('click', () => {
        if (film.paused) film.play().catch(() => { fallback.hidden = false; }); else film.pause();
    });
    audio.addEventListener('click', () => { film.muted = !film.muted; });
    enter.addEventListener('click', event => { event.preventDefault(); reveal(); });
    film.addEventListener('ended', reveal);
    for (const event of ['play', 'pause', 'volumechange']) film.addEventListener(event, update);
    film.addEventListener('error', () => { fallback.hidden = false; });
    entry.addEventListener('keydown', event => {
        if (event.key === 'Escape') { event.preventDefault(); reveal(); }
        if (event.key !== 'Tab') return;
        const controls = [toggle, audio, enter];
        const index = controls.indexOf(document.activeElement);
        if (event.shiftKey && index <= 0) { event.preventDefault(); enter.focus(); }
        else if (!event.shiftKey && (index === controls.length - 1 || index < 0)) { event.preventDefault(); toggle.focus(); }
    });
    reduce.addEventListener('change', event => { if (event.matches) reveal(); });
    document.addEventListener('visibilitychange', () => { if (document.hidden) film.pause(); });
    update();
    film.play().catch(() => { fallback.hidden = false; update(); });
})();

/** Il trailer richiesto dall'utente usa il dialogo nativo e si arresta alla chiusura. */
(() => {
    const dialog = document.querySelector('[data-trailer-dialog]');
    if (!dialog) return;
    const film = dialog.querySelector('[data-full-trailer]');
    let opener;
    document.querySelectorAll('[data-open-trailer]').forEach(button => button.addEventListener('click', () => {
        opener = button;
        document.querySelector('[data-entry-video]')?.pause();
        dialog.showModal();
        film.currentTime = 0;
        film.muted = false;
        film.play().catch(() => {});
    }));
    dialog.querySelector('[data-close-trailer]').addEventListener('click', () => dialog.close());
    dialog.addEventListener('click', event => { if (event.target === dialog) dialog.close(); });
    dialog.addEventListener('close', () => { film.pause(); film.currentTime = 0; opener?.focus(); });
    document.addEventListener('visibilitychange', () => { if (document.hidden) film.pause(); });
})();

/** Il modulo funziona senza JavaScript; l'anteprima non invia il file a servizi esterni. */
(() => {
    const form = document.querySelector('[data-upload-form]');
    if (!form) return;
    const input = form.querySelector('[data-video-input]');
    const zone = form.querySelector('[data-drop-zone]');
    const label = form.querySelector('[data-file-label]');
    const preview = form.querySelector('[data-video-preview]');
    let previousUrl;
    function selectFile() {
        input.setCustomValidity('');
        if (previousUrl) { URL.revokeObjectURL(previousUrl); previousUrl = undefined; }
        preview.hidden = true;
        const file = input.files[0];
        if (!file) { label.textContent = 'Scegli il tuo video'; return; }
        if (file.size > 50 * 1024 * 1024) input.setCustomValidity('Il video deve pesare al massimo 50 MB.');
        else if (!/\.(mp4|mov|webm)$/i.test(file.name)) input.setCustomValidity('Scegli un video MP4, MOV o WebM.');
        label.textContent = file.name;
        if (!input.checkValidity()) { input.reportValidity(); return; }
        previousUrl = URL.createObjectURL(file); preview.src = previousUrl; preview.hidden = false;
    }
    input.addEventListener('change', selectFile);
    for (const event of ['dragenter', 'dragover']) zone.addEventListener(event, e => { e.preventDefault(); zone.classList.add('dragging'); });
    for (const event of ['dragleave', 'drop']) zone.addEventListener(event, () => zone.classList.remove('dragging'));
    zone.addEventListener('drop', e => {
        e.preventDefault(); if (!e.dataTransfer.files.length) return;
        const transfer = new DataTransfer(); transfer.items.add(e.dataTransfer.files[0]);
        input.files = transfer.files; selectFile();
    });
    form.addEventListener('submit', () => {
        const button = form.querySelector('[data-submit]'); button.disabled = true; button.textContent = 'Invio in corso…';
        form.querySelector('[data-upload-status]').hidden = false; form.querySelector('progress').removeAttribute('value');
    });
    window.addEventListener('pageshow', () => {
        const button = form.querySelector('[data-submit]'); button.disabled = false; button.textContent = 'Invia il provino';
        form.querySelector('[data-upload-status]').hidden = true;
    });
})();

/** Il browser avvisa prima di abbandonare una valutazione modificata e non salvata. */
(() => {
    const form = document.querySelector('[data-evaluation-form]');
    if (!form) return;
    const state = form.querySelector('[data-evaluation-state]');
    const original = new URLSearchParams(new FormData(form)).toString();
    let dirty = false;
    function update() { dirty = new URLSearchParams(new FormData(form)).toString() !== original; state.textContent = dirty ? 'Modifiche non salvate' : ''; }
    form.addEventListener('input', update);
    form.addEventListener('change', update);
    form.addEventListener('submit', () => { dirty = false; state.textContent = 'Salvataggio in corso…'; });
    window.addEventListener('beforeunload', event => { if (dirty) { event.preventDefault(); event.returnValue = ''; } });
})();

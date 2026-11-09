/** L'ingresso conserva il finale originale; i comandi restano disponibili da tastiera senza sovrapposizioni sul film. */
(() => {
    const entry = document.querySelector('[data-entry-film]');
    const film = document.querySelector('[data-entry-video]');
    const shell = document.getElementById('site-shell');
    if (!entry || !film || !shell) return;
    const reduce = window.matchMedia('(prefers-reduced-motion: reduce)');
    const enter = entry.querySelector('[data-enter-landing]');
    const root = document.documentElement;
    const playback = entry.querySelector('[data-entry-progress]');
    const loading = entry.querySelector('[data-entry-buffer]');
    const key = 'nero_ingresso';
    let seen = false;
    let opening = false;
    let finishTimer;
    let wasPlaying = false;
    let hasStarted = false;
    try { seen = sessionStorage.getItem(key) === 'v4'; } catch {}
    const force = new URLSearchParams(location.search).get('ingresso') === '1';
    const bypass = reduce.matches || navigator.connection?.saveData || (!force && (seen || location.hash));
    function progress() {
        if (!playback || !loading) return;
        const duration = film.duration;
        if (!Number.isFinite(duration) || duration <= 0) { loading.removeAttribute('value'); return; }
        const played = Math.min(100, Math.max(0, film.currentTime / duration * 100));
        const buffered = film.buffered.length ? film.buffered.end(film.buffered.length - 1) / duration * 100 : 0;
        playback.value = played; loading.value = Math.min(100, buffered);
        playback.setAttribute('aria-valuetext', Math.round(played) + '% riprodotto');
        loading.setAttribute('aria-valuetext', Math.round(loading.value) + '% caricato');
    }
    for (const event of ['loadedmetadata','durationchange','timeupdate','progress','ended','seeked']) film.addEventListener(event,progress);
    function update() {
        progress();
        film.setAttribute('aria-label', film.muted ? 'Attiva l’audio del trailer' : (film.paused ? 'Riproduci il trailer con audio' : 'Metti in pausa il trailer'));
        film.setAttribute('aria-pressed', String(!film.paused));
    }
    function reveal() {
        if (opening || entry.hidden) return;
        opening = true;
        clearTimeout(finishTimer);
        film.pause();
        try { sessionStorage.setItem(key, 'v4'); } catch {}
        shell.inert = false;
        shell.removeAttribute('aria-hidden');
        root.classList.add('film-opening');
        function finish() {
            entry.hidden = true;
            root.classList.remove('film-start', 'film-opening');
            window.scrollTo({top:0,behavior:'instant'});
            document.getElementById('project-title')?.focus({preventScroll:true});
        }
        if (reduce.matches) finish(); else window.setTimeout(finish,1450);
    }
    if (bypass) { entry.hidden = true; return; }
    root.classList.add('film-start');
    shell.inert = true;
    shell.setAttribute('aria-hidden','true');
    entry.setAttribute('role','dialog');
    entry.setAttribute('aria-modal','true');
    entry.setAttribute('aria-labelledby','entry-title');
    film.controls = false;
    film.muted = false;
    film.volume = 1;
    film.tabIndex = 0;
    film.setAttribute('role','button');
    entry.focus({preventScroll:true});
    /** Il blocco dell'audio automatico dipende dal browser: un tocco abilita l'audio, senza cambiare le preferenze del visitatore. */
    async function start() {
        if (opening || entry.hidden || document.hidden) return;
        try { await film.play(); }
        catch (error) {
            if (opening || entry.hidden) return;
            if (error.name === 'NotAllowedError') {
                film.muted = true;
                try { await film.play(); } catch { update(); }
            } else if (error.name !== 'AbortError') reveal();
        }
        update();
    }
    function interact() {
        if (opening) return;
        if (film.muted) { film.muted = false; start(); }
        else if (film.paused) start();
        else film.pause();
    }
    film.addEventListener('click',interact);
    enter.addEventListener('click',event=>{event.preventDefault();reveal();});
    film.addEventListener('ended',()=>{finishTimer=window.setTimeout(reveal,700);});
    for (const event of ['play','pause','volumechange']) film.addEventListener(event,update);
    film.addEventListener('play',()=>{hasStarted=true;});
    entry.addEventListener('error',reveal,true);
    entry.addEventListener('keydown',event=>{
        if (event.key === 'Escape') { event.preventDefault();reveal(); }
        else if (event.code === 'Space' || (event.key === 'Enter' && event.target === film)) { event.preventDefault();interact(); }
        else if (event.key.toLowerCase() === 'm') { event.preventDefault();film.muted=!film.muted; }
        else if (event.key === 'Tab') {
            if (event.shiftKey && document.activeElement !== enter) { event.preventDefault();enter.focus(); }
            else if (!event.shiftKey && document.activeElement !== film) { event.preventDefault();film.focus(); }
        }
    });
    reduce.addEventListener('change',event=>{if(event.matches)reveal();});
    document.addEventListener('visibilitychange',()=>{
        if (document.hidden) { wasPlaying=!film.paused;film.pause(); }
        else if (wasPlaying || !hasStarted) { wasPlaying=false;start(); }
    });
    update();start();
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

/** Il codice nel frammento non viene trasmesso nei log di navigazione. */
(()=>{const form=document.querySelector('[data-activation-form]');if(!form)return;const token=new URLSearchParams(location.hash.slice(1)).get('token');if(token){form.querySelector('[name=token]').value=token;history.replaceState(null,'',location.pathname+location.search);}})();
/** La foto viene mostrata soltanto nel browser prima dell'invio. */
(()=>{const input=document.querySelector('[data-photo-input]'),preview=document.querySelector('[data-photo-preview]');if(!input||!preview)return;let url;input.addEventListener('change',()=>{if(url)URL.revokeObjectURL(url);preview.hidden=true;input.setCustomValidity('');const file=input.files[0];if(!file)return;if(file.size>5*1024*1024){input.setCustomValidity('La foto deve pesare al massimo 5 MB.');input.reportValidity();return;}url=URL.createObjectURL(file);preview.src=url;preview.hidden=false;});})();

/** Il ritorno in cima compare soltanto dopo lo scorrimento; il movimento ridotto resta rispettato dal browser. */
(() => {
    const link = document.querySelector('[data-back-top]');
    if (!link) return;
    const visible = new Set();
    function update() {
        link.hidden = scrollY < 500 || visible.size > 0;
        if (link.hidden) return;
        const box = link.getBoundingClientRect();
        const coversContent = [...document.querySelectorAll('.nero-credits,.nero-curtain-copy>p')].some(element => {
            const r = element.getBoundingClientRect();
            return box.left < r.right && box.right > r.left && box.top < r.bottom && box.bottom > r.top;
        });
        if (coversContent) { link.hidden = true; return; }
        const panel = document.elementsFromPoint(box.x + box.width / 2, box.y + box.height / 2).map(element => element.closest('.landing-panel')).find(Boolean);
        link.dataset.onDark = String(Boolean(panel?.matches('.roma-cover,.scene-panel,.nero-curtain,.nero-invitation')));
    }
    const observer = new IntersectionObserver(entries => {
        entries.forEach(entry => { if (entry.isIntersecting) visible.add(entry.target); else visible.delete(entry.target); });
        update();
    });
    document.querySelectorAll('.site-footer,.application-page .submit-row').forEach(element => observer.observe(element));
    addEventListener('scroll', update, { passive: true });
    addEventListener('resize', update, { passive: true });
    update();
})();

if(document.body.classList.contains('cinema-home'))document.documentElement.classList.add('landing-scroll');

/** La password resta mascherata all'apertura e durante invio o navigazione, senza alterare il valore inserito. */
(() => {
    const fields = [];
    document.querySelectorAll('[data-password-toggle]').forEach(button => {
        const input = document.getElementById(button.getAttribute('aria-controls'));
        if (!input || input.type !== 'password') return;
        function setVisible(visible) {
            input.type = visible ? 'text' : 'password';
            button.setAttribute('aria-pressed', String(visible));
            button.setAttribute('aria-label', visible ? 'Nascondi la password' : 'Mostra la password');
            button.querySelector('[data-password-slash]').toggleAttribute('hidden', !visible);
        }
        button.hidden = false;
        button.addEventListener('click', () => setVisible(input.type === 'password'));
        input.form?.addEventListener('submit', () => setVisible(false));
        fields.push(setVisible);
    });
    addEventListener('pagehide', () => fields.forEach(setVisible => setVisible(false)));
})();

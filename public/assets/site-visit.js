/** Una pagina ripristinata dalla memoria del browser richiede un nuovo controllo prima di mostrare il contenuto. */
addEventListener('pagehide', () => { document.body.hidden = true; });
addEventListener('pageshow', event => {
    if (!event.persisted) return;
    const entry = new URL(location.href);
    entry.searchParams.set('_site_entry', '1');
    location.replace(entry.href);
});

# NERONE

Applicazione Laravel per la presentazione del progetto NERONE, l'invio di candidature video e la gestione riservata dei provini.

La pagina pubblica utilizza il trailer fornito e le immagini del progetto visivo Laccioland Studio. Il PDF originale resta fuori dalla cartella pubblica. Le risorse effettivamente utilizzate sono in `public/assets/nerone`.

## Avvio

Usare un runtime PHP che includa le correzioni ufficiali di sicurezza, con le estensioni richieste da Laravel e PDO MySQL. La verifica locale è stata eseguita con PHP 8.5.12, pacchetto Windows 8.5.12-1.

La connessione al database, la chiave dell'applicazione e le impostazioni dell'ambiente sono in `.env`, che deve restare privato. Il server deve esporre esclusivamente `public`.

Da questa cartella, con il runtime corretto disponibile:

```sh
php artisan serve --host=127.0.0.1 --port=8741
```

## Casting

Il login richiede un account esistente con il permesso di operatore casting. Non sono presenti registrazione o configurazione pubbliche. L'impostazione `CASTING_LOCAL_ACCESS_ONLY` mantiene l'area riservata limitata al computer locale.

I video delle candidature vengono salvati fuori dalla cartella pubblica. Il loro streaming richiede una sessione autorizzata. La produzione può cercare i candidati, salvare note e valutazioni ed eliminare scheda e video con conferma esplicita.

## Contenuti da completare

Il titolare del trattamento e il recapito ufficiale sono da confermare. I relativi campi sono in `config/casting.php`. L'informativa è indicata come in aggiornamento e deve essere completata prima dell'apertura al pubblico. Profili ricercati, requisiti specifici e calendario non vengono inventati.

Prima di una pubblicazione occorre verificare l'hosting effettivo, usare HTTPS, credenziali robuste e un utente database con permessi limitati. La documentazione e i materiali creativi forniti mantengono i diritti dei rispettivi autori; le licenze delle dipendenze software non si estendono a tali materiali.

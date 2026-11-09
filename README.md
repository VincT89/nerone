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

Per avviare su questo computer da Git Bash: `bash avvia.sh`. Da PowerShell: `./avvia.ps1`. Questi comandi selezionano il runtime portatile aggiornato gia predisposto fuori dal progetto; non cambiano il PHP del sistema. Su altri computer impostare `PHP_BIN` oppure fornire `-PhpPath` a PowerShell. La porta predefinita e 8741.

Il video di ingresso usa il filmato originale completo, con data finale e audio richiesto alla partenza. Se il browser blocca l audio automatico, un tocco sul video lo attiva. Non ci sono pulsanti sovrapposti: il video si puo mettere in pausa toccandolo o con Spazio, M gestisce l audio ed Esc apre la pagina. Il collegamento per saltare il film diventa visibile con la navigazione da tastiera. La preferenza di movimento ridotto evita l ingresso automatico. La memoria della scheda evita di ripetere il film a ogni ritorno. Logo, immagini e subset del carattere derivano dal PDF fornito.

## Casting

Il login richiede un account esistente con il permesso di operatore casting. Non sono presenti registrazione o configurazione pubbliche. L'impostazione `CASTING_LOCAL_ACCESS_ONLY` mantiene l'area riservata limitata al computer locale.

I video delle candidature vengono salvati fuori dalla cartella pubblica. Il loro streaming richiede una sessione autorizzata. La produzione può cercare i candidati, salvare note e valutazioni ed eliminare scheda e video con conferma esplicita.

## Contenuti da completare

Il titolare del trattamento e il recapito ufficiale sono da confermare. I relativi campi sono in `config/casting.php`. L'informativa è indicata come in aggiornamento e deve essere completata prima dell'apertura al pubblico. Profili ricercati, requisiti specifici e calendario non vengono inventati.

Prima di una pubblicazione occorre verificare l'hosting effettivo, usare HTTPS, credenziali robuste e un utente database con permessi limitati. La documentazione e i materiali creativi forniti mantengono i diritti dei rispettivi autori; le licenze delle dipendenze software non si estendono a tali materiali.


## Area personale e posta locale

Il modulo iniziale raccoglie dati, profilo artistico e una foto facoltativa. I video e i documenti vengono richiesti successivamente dalla produzione. Il gestionale mostra una scheda per candidatura e conteggi reali per profilo.

La pagina Posta locale permette agli operatori di provare le comunicazioni e il collegamento di attivazione senza inviare email esterne. E' disponibile solo sul computer locale con CASTING_LOCAL_ACCESS_ONLY attivo e APP_URL locale. I messaggi sono cifrati nel database. I collegamenti di attivazione scadono dopo 48 ore, sono monouso e consentono al candidato di scegliere una password; le password non vengono inviate per email. La posta reale dovra essere configurata prima della pubblicazione.

Le sessioni dei candidati e degli operatori sono distinte. Tutte le foto e tutti gli allegati restano privati. La cancellazione di una scheda elimina anche allegati, richieste, comunicazioni e il relativo account.


Il collegamento Login apre un unico modulo: le credenziali degli operatori portano al gestionale, quelle dei candidati alla propria area. Non viene richiesta una scelta del ruolo.


## Fasi di selezione

Ogni casting puo avere un numero libero di fasi successive con nome, inizio e fine in orario italiano. Le date sono conservate in UTC. La prima fase regola l apertura delle nuove candidature. La produzione ammette esplicitamente il candidato alla fase seguente e il passaggio viene registrato. Ogni richiesta appartiene a una fase e il candidato puo rispondere nel relativo periodo. I dati restano separati per candidatura e selezione.

La tabella contiene foto, profilo, fase, stato e data di ricezione, con filtri e paginazione. I limiti delle richieste di accesso sono separati da quelli degli altri moduli. Il sito pubblico usa pannelli proporzionati alla schermata con aggancio allo scorrimento; testi ingranditi e contenuti espansi mantengono lo scorrimento naturale.

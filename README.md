# NERO!

Sito Laravel del musical NERO! con presentazione del progetto, candidature online e area riservata alla produzione. Le immagini, i caratteri e il trailer sono serviti da `public/assets`.

## Avvio locale

Le dipendenze bloccate da Composer richiedono PHP 8.4.1 o successivo. Usare una build con le correzioni di sicurezza e il driver PDO MySQL. Il collaudo locale usa PHP 8.4.27. Su Windows, se sono installate più versioni di PHP, impostare `PATH` e `PHPRC` nelle impostazioni del terminale della sola area di lavoro in VS Code. Aprire un nuovo terminale e verificare la versione, il `php.ini` caricato e il driver MySQL.

```sh
php -v
php --ini
php --ri pdo_mysql
composer install
```

Per una nuova installazione, creare `.env` a partire da `.env.example` e configurare il database. Generare la chiave soltanto se manca e applicare le migrazioni.

```sh
php artisan key:generate
php artisan migrate
php artisan serve
```

La home e il gestionale caricano direttamente i file in `public/assets` e si aprono con `php artisan serve`. Conservare `.env` fuori da Git.

## Candidature

La home e il modulo seguono lo stato, la data di apertura e la scadenza salvati in **Apertura audizioni**. Se il campo di apertura è vuoto, si usa il 17 novembre 2026. La migrazione inizializza al 2 dicembre 2026 la scadenza della selezione corrente se il campo è vuoto. Il sito mostra il giorno di scadenza. Le scadenze impostate alle 00:00 o alle 23:59 includono tutta la giornata, in ora italiana. Prima dell'apertura e dopo la chiusura il modulo resta consultabile.

La candidatura richiede un self-tape fino a 5 minuti e 100 MB, il curriculum in PDF e due foto recenti. L'invio è protetto da un CAPTCHA locale e limitato a una candidatura completata per connessione ogni ora. Foto, video e documenti vengono conservati nello spazio privato dell'applicazione. Gli operatori autorizzati consultano, filtrano, commentano e votano le candidature. Gli amministratori possono eliminarle con conferma esplicita. Se un allegato non viene rimosso, la cancellazione resta in sospeso e si può riprendere dal gestionale.

Per collaudare il gestionale, caricare `foto-primo-piano.jpg`, `foto-figura-intera.jpg`, `cv-prova-nero.pdf` e `self-tape-prova-nero.mp4` in `storage/app/private/prova-nero`. Su Plesk, nella scheda **Artisan**, inserire `db:seed --class=ProvaNeroSeeder --force`. Il comando crea una sola scheda fittizia **Prova Nero**. La cancellazione della scheda dal gestionale rimuove anche i quattro file. Per verificare CAPTCHA, invio e conversione video, usare il modulo pubblico durante un periodo di apertura.

## Accesso della produzione

**Area riservata** nel footer apre il login della produzione. In locale `CASTING_LOCAL_ACCESS_ONLY=true` limita l'accesso alle richieste provenienti dal computer locale. Sul server, `CASTING_LOCAL_ACCESS_ONLY=false` consente alla produzione di accedere da remoto con le proprie credenziali.

Per il primo accesso, impostare una password temporanea in `CASTING_ADMIN_PASSWORD` nel file `.env` privato. Se la configurazione è in cache, eseguire prima `php artisan config:clear`. Dopo le migrazioni avviare il seeder.

```sh
php artisan db:seed --class=CastingAdminSeeder
```

Il seeder crea `admin@admin.it` se il gestionale è privo di amministratori e salva la password come hash. Rimuovere la variabile dopo la creazione e rigenerare l'eventuale cache di configurazione. In **Collaboratori**, creare un amministratore personale, accedere con quello e rimuovere l'account iniziale.

Gli amministratori possono creare account, modificare nome ed email e assegnare il ruolo di amministratore o collaboratore. Il gestionale tutela l'ultimo amministratore e richiede che la cancellazione sia eseguita da un altro amministratore. Eliminare un account elimina anche i suoi voti e commenti.

## Pubblicazione su Plesk

Configurare il sottodominio affinché esponga solo `public`. Impostare `APP_ENV=production`, `APP_DEBUG=false` e `APP_URL` con l'indirizzo HTTPS effettivo. Rendere scrivibili da PHP `storage` e `bootstrap/cache` e mantenere gli allegati fuori dalla cartella pubblica.

Verificare la versione PHP effettiva del sottodominio e quella usata dalle attività pianificate. Le release ufficiali 8.4.27 e 8.5.12 includono le correzioni per CVE-2026-109618 e CVE-2026-109619. Per una build con correzioni applicate dal fornitore, verificare il relativo avviso. [Changelog PHP](https://www.php.net/ChangeLog-8.php#8.4.27)

Per l'invio dei provini, impostare almeno `upload_max_filesize=100M` e `post_max_size=160M` e controllare anche il limite del server web. FFmpeg deve essere disponibile al processo PHP, tramite PATH o `CASTING_FFMPEG_BINARY`.

Dopo le migrazioni, configurare una sola attività Plesk ogni minuto per eseguire il comando seguente dalla cartella dell'applicazione con il PHP previsto per il sito.

```sh
php artisan schedule:run
```

Il worker della coda `self-tapes` usa il database e si arresta a coda vuota oppure dopo cinque minuti al termine del lavoro in corso. Il blocco dello scheduler scade dopo 30 minuti. La compressione cerca di ridurre il peso del video conservando l'audio quando presente e mantiene il file ricevuto se la conversione fallisce o non riduce il peso. La coda riprova dopo 15, 45 e 120 secondi. Una prenotazione interrotta può essere ripresa dopo 20 minuti. Provare un invio e il relativo job sul sottodominio prima di aprire le candidature.

## Privacy e cookie

Il titolare indicato è Nero Musical srl. Completare in `config/casting.php` il recapito ufficiale e aggiornare nell'informativa privacy le informazioni sull'hosting effettivo prima dell'apertura. I link **Privacy** e **Cookie** sono nel footer. La cookie policy descrive i cookie tecnici e la memoria della scheda usata per il trailer. [Linee guida del Garante sui cookie](https://www.garanteprivacy.it/home/docweb/-/docweb-display/docweb/9677876)

## Indicizzazione

Con `APP_ENV=production` la home, la pagina delle candidature e le informative sono indicizzabili. Il login, il gestionale e le ricevute di candidatura restano esclusi tramite `X-Robots-Tag`. Negli ambienti di prova Laravel invia la stessa direttiva su tutte le pagine.

Dopo la pubblicazione del sottodominio HTTPS, verificare la proprietà in Google Search Console e richiedere l'indicizzazione della home e della pagina delle candidature. [Richiedere la scansione a Google](https://developers.google.com/search/docs/crawling-indexing/ask-google-to-recrawl)

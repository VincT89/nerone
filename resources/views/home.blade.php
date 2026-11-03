@extends('layout')
@section('title', 'Il progetto')
@section('body-class', 'nerone-home')
@section('entry-film')
<section class="entry-film" data-entry-film aria-label="Ingresso video al progetto" tabindex="-1">
    <h2 class="sr-only" id="entry-title">Il trailer di NERONE</h2>
    <video id="entry-video" data-entry-video controls muted playsinline preload="metadata" poster="{{ asset('assets/nerone/arco.jpg') }}" aria-label="Una figura dell’antica Roma emerge attraverso un arco luminoso">
        <source src="{{ asset('assets/nerone/trailer.mp4') }}" type="video/mp4">
    </video>
    <div class="entry-controls"><div><button type="button" data-entry-toggle aria-controls="entry-video">Riproduci</button><button type="button" data-entry-audio aria-pressed="false">Attiva audio</button></div><a href="#progetto" data-enter-landing>Entra nel progetto</a></div>
    <p class="entry-fallback" data-entry-fallback role="status" hidden>Puoi riprodurre il trailer oppure entrare subito nel progetto.</p>
</section>
@endsection
@section('content')
<section class="landing-vision" id="progetto" aria-labelledby="project-title">
    <figure><img src="{{ asset('assets/nerone/teatro.jpg') }}" width="1376" height="768" alt="La figura romana in controluce apre le braccia verso la platea del teatro" fetchpriority="high"></figure>
    <div class="vision-heading"><h1 id="project-title" tabindex="-1">NERONE</h1><p>L’antica Roma entra<br>in una nuova scena.</p></div>
    <a class="vision-discover" href="#racconto">Scopri il progetto</a>
</section>

<section class="project-story" id="racconto" aria-labelledby="story-title">
    <h2 id="story-title">Il pubblico<br>dentro la scena.</h2>
    <div><p>Il teatro diventa parte del racconto. NERONE immagina un’esperienza immersiva in cui i performer attraversano il palco, la platea e la galleria, avvicinando il pubblico all’azione.</p><p>La forza dell’immaginario romano incontra luce, trasparenze e materiali contemporanei. Lo spazio cambia, si apre e accompagna la narrazione.</p></div>
</section>

<section class="newclassic-project" id="nuovo-classico" aria-labelledby="classic-title">
    <header class="newclassic-heading"><h2 id="classic-title">Nuovo<br>classico.</h2><div><p>Archi, colonne e geometrie classiche vengono reinterpretati attraverso strutture leggere, superfici trasparenti e profili luminosi.</p><p>Le immagini del progetto creativo raccontano questa ricerca: l’architettura diventa luce e il teatro si trasforma in paesaggio.</p></div></header>
    <div class="newclassic-images">
        <figure class="classic-architecture"><img src="{{ asset('assets/nerone/classico-archi.jpg') }}" width="736" height="567" alt="Un’architettura trasparente ad archi sospesa all’interno di un teatro" loading="lazy"><figcaption>Archi, trasparenze e spazio teatrale.</figcaption></figure>
        <figure class="classic-columns"><img src="{{ asset('assets/nerone/classico-colonne.jpg') }}" width="736" height="693" alt="Colonne costruite da elementi verticali luminosi" loading="lazy"><figcaption>La colonna reinterpretata attraverso la luce.</figcaption></figure>
        <figure class="classic-stage"><img src="{{ asset('assets/nerone/classico-scena.jpg') }}" width="1199" height="643" alt="Una scena teatrale attraversata da archi e segni di luce" loading="lazy"><figcaption>Riferimenti scenografici dal progetto visivo.</figcaption></figure>
    </div>
</section>

<section class="casting-call" id="il-provino" aria-labelledby="audition-title">
    <div class="call-heading"><h2 id="audition-title">Il tuo provino.<br>La tua presenza<br>in scena.</h2><a class="button primary" href="{{ route('audition.apply') }}">Invia la candidatura</a></div>
    <div class="call-information"><p>Presenta il tuo percorso artistico e carica il video della tua esibizione per NERONE. Il materiale sarà raccolto in una scheda riservata alla produzione.</p><p class="casting-information">{{ config('casting.project.profiles') ?? 'Profili ricercati, requisiti specifici e calendario della selezione sono in aggiornamento.' }}</p><div class="call-video"><h3>Prepara il tuo video.</h3><dl><div><dt>La ripresa</dt><dd>Buona luce, inquadratura stabile e un ambiente silenzioso.</dd></div><div><dt>Il file</dt><dd>MP4, MOV o WebM fino a 50 MB. Puoi rivederlo nel modulo prima dell’invio.</dd></div></dl><p>Il video resta riservato alla produzione.</p></div></div>
</section>

<dialog class="trailer-dialog" data-trailer-dialog aria-labelledby="trailer-title"><div class="dialog-header"><h2 id="trailer-title">Il trailer di NERONE</h2><button type="button" data-close-trailer autofocus>Chiudi trailer</button></div><video controls playsinline preload="none" data-full-trailer aria-label="Trailer completo di NERONE"><source src="{{ asset('assets/nerone/trailer.mp4') }}" type="video/mp4"></video></dialog>
@endsection

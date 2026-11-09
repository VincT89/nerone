@extends('layout')
@section('title','Il progetto')
@section('body-class','nero-home cinema-home')
@section('entry-film')
<section class="entry-film" data-entry-film aria-label="Ingresso video al progetto" tabindex="-1"><h2 class="sr-only" id="entry-title">Il trailer di NERO</h2><p class="sr-only" id="entry-help">Tocca il video per attivare l’audio o mettere in pausa. Da tastiera: Spazio per la riproduzione, M per l’audio, Esc per entrare nel sito.</p><video id="entry-video" data-entry-video controls playsinline preload="auto" poster="{{ asset('assets/nero/ingresso.jpg') }}" aria-label="Trailer completo di NERO, con data finale" aria-describedby="entry-help"><source src="{{ asset('assets/nero/trailer-nero.mp4') }}" type="video/mp4"></video><a class="film-skip" href="#progetto" data-enter-landing>Salta</a><div class="entry-progress"><progress max="100" value="0" data-entry-buffer aria-label="Caricamento del video"></progress><progress max="100" value="0" data-entry-progress aria-label="Avanzamento del video"></progress></div></section>
@endsection
@section('content')
<section class="roma-cover landing-panel" id="progetto" aria-labelledby="project-title">
    <figure class="cover-presence" role="img" aria-label="Una figura romana emerge da un arco luminoso">
        <svg class="hero-wide" viewBox="0 0 2048 1143" preserveAspectRatio="xMidYMid meet" aria-hidden="true"><image href="{{ asset('assets/nero/hero.jpg') }}" width="2048" height="1143"/></svg>
        <!-- L'inquadratura verticale esclude i margini neri e conserva l'intero arco con la figura. -->
        <svg class="hero-tall" viewBox="620 0 860 1143" preserveAspectRatio="xMidYMid meet" aria-hidden="true"><image href="{{ asset('assets/nero/hero.jpg') }}" width="2048" height="1143"/></svg>
    </figure>
    <div class="cover-words"><p>Un nuovo<br>classico.</p><div class="cover-title"><h1 id="project-title" tabindex="-1">NERO</h1><p>Audizioni per il cast.</p></div></div>
    <div class="cover-bottom"><button type="button" data-open-trailer>Guarda il video</button></div>
</section>
<section class="nero-casting landing-panel" id="nuovo-classico" aria-labelledby="casting-title">
    <div class="nero-casting-layout">
        <div class="nero-casting-copy"><h2 id="casting-title">Il palco.<br>La tua presenza.</h2><p>NERO prende forma anche attraverso i suoi interpreti. La ricerca del cast comincia da qui.</p><ul class="nero-disciplines" aria-label="Discipline artistiche"><li>Canto</li><li>Recitazione</li><li>Danza</li><li>Musica</li></ul></div>
        <div class="nero-casting-images"><figure class="nero-theatre-photo"><img src="{{ asset('assets/nero/teatro.jpg') }}" width="2000" height="1333" alt="Un grande palcoscenico e la platea, fotografia di repertorio" loading="lazy"></figure><figure class="nero-casting-detail"><img src="{{ asset('assets/nero/platea.jpg') }}" width="1600" height="2133" alt="Ordini di palchi di un grande teatro, fotografia di repertorio" loading="lazy"></figure></div>
    </div>
</section>
<section class="nero-curtain landing-panel" id="fuoco" aria-labelledby="curtain-title">
    <figure class="nero-curtain-photo"><img src="{{ asset('assets/nero/sipario.jpg') }}" width="1600" height="2400" alt="Sipario e architettura di una grande sala teatrale, fotografia di repertorio" loading="lazy"></figure>
    <div class="nero-curtain-copy"><h2 id="curtain-title">Prima<br>del sipario.</h2><p>La voce, il corpo, l’interpretazione.<br>Il primo incontro con NERO è la tua audizione.</p></div>
</section>
<section class="nero-staff landing-panel" id="staff" aria-labelledby="staff-title">
    <div class="nero-staff-layout">
        <header><h2 id="staff-title">Il gruppo<br>creativo.</h2><p>Le persone dietro NERO.</p><p class="nero-staff-notice">I crediti sono in aggiornamento.</p><figure class="nero-staff-photo"><img src="{{ asset('assets/nero/palchi.jpg') }}" width="1600" height="2400" alt="Palchi e dettagli architettonici di un teatro, fotografia di repertorio" loading="lazy"></figure></header>
        <dl class="nero-credits">@foreach(config('casting.project.staff',[]) as $person)<div><dt>{{ $person['role'] }}</dt><dd @class(['credit-pending'=>empty($person['name'])])>{{ $person['name'] ?: 'Da annunciare' }}</dd></div>@endforeach</dl>
    </div>
</section>
<section class="nero-invitation landing-panel" id="il-provino" aria-labelledby="audition-title">
    <figure class="nero-invitation-photo"><img src="{{ asset('assets/nero/audizione.jpg') }}" width="1376" height="768" alt="Una figura sul palco davanti alla platea di un grande teatro" loading="lazy"></figure>
    <div class="nero-invitation-action"><h2 id="audition-title">La tua<br>audizione.</h2><a class="nero-action-main" href="{{ route('audition.apply') }}">Partecipa</a></div>
</section>

<dialog class="trailer-dialog" data-trailer-dialog aria-labelledby="trailer-title"><div class="dialog-header"><h2 id="trailer-title" class="sr-only">Il video di NERO</h2><button type="button" data-close-trailer autofocus aria-label="Chiudi il video"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m5 5 14 14M19 5 5 19"/></svg></button></div><video controls playsinline preload="none" data-full-trailer aria-label="Trailer completo di NERO, con data finale"><source src="{{ asset('assets/nero/trailer-nero.mp4') }}" type="video/mp4"></video></dialog>
@endsection

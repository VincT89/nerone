@extends('layout')
@section('title','Cookie policy')
@section('public-nav-start')
    <a class="nav-back-project" href="{{ route('home') }}#progetto">Torna indietro</a>
@endsection
@section('content')
<article class="legal-page">
    <h1>Cookie policy</h1>
    <p>Il portale usa cookie tecnici di prima parte per far funzionare i moduli, proteggere le richieste e gestire l’accesso all’area casting.</p>

    <h2>Cookie utilizzati</h2>
    <dl class="legal-cookies">
        <div>
            <dt><code>{{ config('session.cookie') }}</code></dt>
            <dd>Identifica la sessione, mantiene l’accesso e gestisce i messaggi dei moduli.</dd>
            <dd>Durata: {{ config('session.expire_on_close') ? 'fino alla chiusura del browser' : config('session.lifetime').' minuti dall’ultima attività' }}.</dd>
        </div>
        <div>
            <dt><code>XSRF-TOKEN</code></dt>
            <dd>Protegge le richieste dai tentativi di invio da siti esterni.</dd>
            <dd>Durata: {{ config('session.lifetime') }} minuti.</dd>
        </div>
    </dl>

    <h2>Memoria della scheda del browser</h2>
    <p>La voce tecnica <code>nero_ingresso</code>, salvata in <code>sessionStorage</code>, ricorda che hai già aperto il progetto per evitare di ripetere il video introduttivo nella stessa scheda. La voce contiene solo un indicatore di apertura e viene eliminata quando termina la sessione della scheda. Puoi entrare nel progetto anche usando il collegamento presente nel video.</p>

    <h2>Profilazione e servizi esterni</h2>
    <p>Il portale non usa cookie pubblicitari, strumenti di profilazione o servizi di analisi del traffico. L’applicazione serve direttamente caratteri, immagini e video.</p>

    <h2>Gestione dei cookie</h2>
    <p>I cookie tecnici sono esenti dal consenso preventivo. Puoi bloccarli o cancellarli dalle impostazioni del browser. Il blocco può impedire l’invio dei moduli e l’accesso all’area riservata.</p>
    <p>I siti raggiunti tramite collegamenti esterni applicano le rispettive informative.</p>

    <h2>Titolare e contatti</h2>
    <p>{{ config('casting.privacy.controller') }}@if(config('casting.privacy.contact'))<br>{{ config('casting.privacy.contact') }}@endif</p>
    <p>Per informazioni sul trattamento dei dati e sull’esercizio dei diritti consulta l’<a href="{{ route('privacy') }}">informativa privacy</a>.</p>
</article>
@endsection

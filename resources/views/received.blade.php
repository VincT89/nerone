@extends('layout')
@section('title','Candidatura ricevuta')
@section('content')
<section class="quiet-page"><h1>La tua candidatura<br>è registrata.</h1><p>La produzione ha ricevuto il tuo profilo e i tre video.</p><p>Il materiale sarà valutato e, in caso di selezione, sarai contattato per il provino dal vivo ai recapiti che hai indicato.</p><p class="muted">Riferimento: {{ substr($reference,0,8) }}</p><a class="button primary" href="{{ route('home') }}#progetto">Torna al progetto</a></section>
@endsection

@extends('layout')
@section('title','Provino ricevuto')
@section('content')
<section class="quiet-page"><h1>Il tuo provino<br>è stato ricevuto.</h1><p>La candidatura e il video sono stati registrati.<br>Grazie per averci fatto conoscere il tuo talento.</p><p class="muted">Riferimento della candidatura: {{ substr($reference,0,8) }}</p><a class="button primary" href="{{ route('home') }}">Torna alla pagina iniziale</a></section>
@endsection

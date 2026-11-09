@extends('layout')
@section('title','Attiva il tuo accesso')
@section('body-class','candidate-access-page')
@section('content')
<section class="candidate-access"><h1>Benvenuto<br>nel progetto.</h1><p>Scegli la password della tua area personale. Il collegamento ricevuto è valido per 48 ore e può essere utilizzato una sola volta.</p>@include('candidate.errors')
<form method="post" action="{{ route('candidate.password') }}" class="login-form" data-activation-form>@csrf<input type="hidden" name="token" value="{{ old('token') }}"><label>La tua password<input type="password" name="password" autocomplete="new-password" minlength="12" required></label><p class="muted">Almeno 12 caratteri, con lettere e numeri.</p><label>Conferma la password<input type="password" name="password_confirmation" autocomplete="new-password" minlength="12" required></label><button class="button primary">Attiva la tua area</button></form><p><a href="{{ route('candidate.login') }}">Hai già una password o ti serve un nuovo accesso?</a></p></section>
@endsection

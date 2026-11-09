@extends('layout')
@section('title','Area candidato')
@section('body-class','candidate-access-page')
@section('content')
<section class="candidate-access"><h1>La tua area.</h1><p>Accedi per leggere le richieste della produzione e inviare i materiali della tua candidatura.</p>@include('candidate.errors')
<form method="post" action="{{ route('candidate.enter') }}" class="login-form">@csrf<label>Email<input type="email" name="email" autocomplete="email" value="{{ old('email') }}" required></label><label>Password<input type="password" name="password" autocomplete="current-password" required></label><button class="button primary">Accedi alla tua area</button></form>
<details class="candidate-recover"><summary>Non riesci ad accedere?</summary><form method="post" action="{{ route('candidate.recover') }}">@csrf<label>Email della candidatura<input type="email" name="email" required autocomplete="email"></label><button class="button primary">Richiedi un nuovo accesso</button></form></details></section>
@endsection

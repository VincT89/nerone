@extends('layout')
@section('title','Area riservata')
@section('body-class','casting-login-page')
@section('content')
<section class="casting-access"><figure class="access-stage"><img src="{{ asset('assets/nero/platea.jpg') }}" width="1600" height="2133" alt="Palchi di una grande sala teatrale, fotografia di repertorio"></figure><div class="access-form"><h1>Produzione.</h1><p>Accedi alle candidature e confrontati con il gruppo di valutazione.</p>@include('candidate.errors')<form class="login-form" method="post" action="{{ route('login.enter') }}">@csrf<label>Email<input type="email" name="email" value="{{ old('email') }}" autocomplete="username" required maxlength="254"></label><x-password-field id="login-password" required /><button class="button primary">Accedi</button></form></div></section>
@endsection

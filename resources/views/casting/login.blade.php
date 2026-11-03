@extends('layout')
@section('title','Accesso casting')
@section('body-class','casting-login-page')
@section('content')
<section class="casting-access">
    <figure class="access-stage"><img src="{{ asset('assets/nerone/classico-scena.jpg') }}" width="1199" height="643" alt="Archi e segni luminosi nella scena del progetto creativo"></figure>
    <div class="access-form"><p class="access-context">Accesso riservato alla produzione</p><h1>La sala<br>casting.</h1><p>Provini, candidature e valutazioni.</p>
        <form action="{{ route('casting.enter') }}" method="post" class="login-form">@csrf
            @if($errors->any())<div class="form-errors" role="alert">{{ $errors->first() }}</div>@endif
            <label for="email">Email</label><input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="username" maxlength="254" required>
            <label for="password">Password</label><input id="password" name="password" type="password" autocomplete="current-password" maxlength="200" required>
            <button class="button primary">Entra nell’area casting</button>
        </form>
    </div>
</section>
@endsection

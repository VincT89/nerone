@extends('layout')
@section('title','Modifica account')
@section('body-class','casting-page')
@section('casting-nav','yes')
@section('content')
<div class="production-shell">
    @include('casting.navigation')
    <header class="production-heading">
        <div>
            <a class="back-link" href="{{ route('casting.team') }}">Torna ai collaboratori</a>
            <h1>Modifica account</h1>
        </div>
    </header>
    @include('candidate.errors')
    <form class="production-settings team-edit" action="{{ route('casting.team.update', $operator) }}" method="post">
        @csrf
        @method('PATCH')
        <label>Nome<input name="name" value="{{ old('name', $operator->name) }}" maxlength="100" required autocomplete="name"></label>
        <label>Email<input name="email" type="email" value="{{ old('email', $operator->email) }}" maxlength="254" required autocomplete="username"></label>
        @if($roleLocked)
            <p>Ruolo Amministratore. Deve restare almeno un amministratore.</p>
        @else
            <label>
                Ruolo
                <select name="role" required>
                    @foreach($roles as $value => $label)
                        <option value="{{ $value }}" @selected(old('role', $operator->is_casting_admin ? 'admin' : 'collaborator') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
        @endif
        <button type="submit" class="button primary">Salva modifiche</button>
    </form>
</div>
@endsection

@extends('layout')
@section('title','Elimina account')
@section('body-class','casting-page casting-delete')
@section('casting-nav','yes')
@section('content')
<section class="quiet-page">
    <a class="back-link" href="{{ route('casting.team') }}">Torna ai collaboratori</a>
    <h1 class="delete-title">Elimina account</h1>
    <p>Verranno eliminati definitivamente l’account di <strong>{{ $operator->name }}</strong> e tutti i suoi voti e commenti.</p>
    @include('candidate.errors')
    <form class="delete-form" action="{{ route('casting.team.destroy', $operator) }}" method="post">
        @csrf
        @method('DELETE')
        <label class="check-field">
            <input name="confirmation" type="checkbox" value="1" required>
            Confermo l’eliminazione dell’account, dei voti e dei commenti.
        </label>
        <button type="submit" class="button danger">Elimina account</button>
    </form>
</section>
@endsection

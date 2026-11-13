@extends('layout')
@section('title','Elimina candidatura')
@section('body-class','casting-page casting-delete')
@section('casting-nav','yes')
@section('content')
@php($pending = $audition->deletion_requested_at !== null)
<section class="quiet-page">
    <a class="back-link" href="{{ $pending ? route('casting.index') : route('casting.show', $audition) }}">{{ $pending ? 'Torna alle candidature' : 'Torna al provino' }}</a>
    <h1 class="delete-title">{{ $pending ? 'Eliminazione in sospeso' : 'Elimina la candidatura' }}</h1>
    @if($pending)
        <p>Completa la rimozione della scheda di <strong>{{ $audition->first_name }} {{ $audition->last_name }}</strong> e degli allegati rimasti.</p>
    @else
        <p>Verranno eliminati la scheda di <strong>{{ $audition->first_name }} {{ $audition->last_name }}</strong>, gli allegati, i commenti e i voti della produzione. L’operazione è definitiva.</p>
    @endif
    <form class="delete-form" action="{{ route('casting.destroy', $audition) }}" method="post">
        @csrf
        @method('DELETE')
        @if($errors->any())<div class="form-errors" role="alert">{{ $errors->first() }}</div>@endif
        <label for="confirmation">Per confermare, scrivi {{ $audition->first_name }} {{ $audition->last_name }}</label>
        <input id="confirmation" name="confirmation" autocomplete="off" required>
        <button class="button danger">{{ $pending ? 'Riprendi eliminazione' : 'Elimina candidatura e allegati' }}</button>
    </form>
</section>
@endsection

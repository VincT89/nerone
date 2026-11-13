@extends('layout')
@section('title','Apertura audizioni')
@section('body-class','casting-page')
@section('casting-nav','yes')
@section('content')
<div class="production-shell">@include('casting.navigation')<header class="production-heading"><div><h1>Apertura audizioni</h1><p>Un’unica selezione dei video, seguita dalle convocazioni dal vivo.</p></div></header>@include('candidate.errors')@if(session('saved'))<p class="production-notice" role="status">{{ session('saved') }}</p>@endif
<form class="production-settings" action="{{ route('casting.settings.update') }}" method="post">@csrf @method('PATCH')
    <label>Nome della selezione<input name="title" value="{{ old('title',$selection->title) }}" maxlength="160" required></label>
    <div class="settings-dates">
        <label>Aperta dal<input type="datetime-local" name="starts_at" value="{{ old('starts_at',$selection->phases->first()?->starts_at?->timezone('Europe/Rome')->format('Y-m-d\TH:i')) }}"></label>
        <label>Aperta fino al<input type="datetime-local" name="ends_at" value="{{ old('ends_at',$selection->phases->first()?->ends_at?->timezone('Europe/Rome')->format('Y-m-d\TH:i')) }}"></label>
    </div>
    <p>Gli invii possono aprire dal <time datetime="{{ $openingDate->toDateString() }}">{{ $openingDate->locale('it')->isoFormat('D MMMM YYYY') }}</time>. Le date e gli orari qui impostati seguono l’ora italiana.</p>
    <input type="hidden" name="is_open" value="0"><label class="check-field"><input type="checkbox" name="is_open" value="1" @checked(old('is_open',$selection->is_open))><span>Consenti l’invio delle candidature nel periodo indicato.</span></label>
    <button class="button primary">Salva apertura</button>
</form></div>
@endsection

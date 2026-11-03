@extends('layout')
@section('title','Elimina candidatura')
@section('casting-nav','yes')
@section('content')
<section class="quiet-page"><a class="back-link" href="{{ route('casting.show',$audition) }}">Torna al provino</a><h1 class="delete-title">Elimina la candidatura</h1><p>Verranno eliminati la scheda di <strong>{{ $audition->first_name }} {{ $audition->last_name }}</strong>, il video e le note della produzione. L’operazione non può essere annullata dall’applicazione.</p><form class="delete-form" action="{{ route('casting.destroy',$audition) }}" method="post">@csrf @method('DELETE')@if($errors->any())<div class="form-errors" role="alert">{{ $errors->first() }}</div>@endif<label for="confirmation">Per confermare, scrivi {{ $audition->first_name }} {{ $audition->last_name }}</label><input id="confirmation" name="confirmation" autocomplete="off" required><button class="button danger">Elimina scheda e video</button></form></section>
@endsection

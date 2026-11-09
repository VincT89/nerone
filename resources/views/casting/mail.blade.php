@extends('layout')
@section('title','Posta locale')
@section('body-class','casting-page')
@section('casting-nav','yes')
@section('content')
<div class="casting-workflow">@include('casting.navigation')<section class="casting-desk local-mail"><a href="{{ route('casting.index') }}">Torna alle candidature</a><h1>Posta locale.</h1><p class="mail-explanation">Le comunicazioni sono disponibili qui e non vengono inviate all’esterno. Questa pagina è riservata alla produzione sul computer locale.</p>
@forelse($messages as $message)<article><header><p>Per {{ $message->candidate->email }} · {{ $message->created_at->timezone('Europe/Rome')->format('d/m/Y H:i') }}</p><h2>{{ $message->subject }}</h2></header><p class="mail-text">{{ $message->payload['text'] }}</p><a class="button primary" href="{{ $message->payload['url'] }}" rel="noreferrer">{{ $message->payload['action'] }}</a><a class="mail-profile-link" href="{{ route('casting.show',$message->audition) }}">Scheda di {{ $message->audition->first_name }} {{ $message->audition->last_name }}</a></article>@empty<p>Nessuna comunicazione ancora predisposta.</p>@endforelse
@if($messages->hasPages())<nav class="pagination">@if($messages->previousPageUrl())<a href="{{ $messages->previousPageUrl() }}">Precedenti</a>@endif @if($messages->nextPageUrl())<a href="{{ $messages->nextPageUrl() }}">Successive</a>@endif</nav>@endif</section></div>
@endsection

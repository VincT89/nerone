@extends('layout')
@section('title','Sala casting')
@section('body-class','casting-page casting-index')
@section('casting-nav','yes')
@section('content')
<div class="casting-workflow">
    @include('casting.navigation',['currentStatus'=>request('status')])
    <section class="casting-desk" aria-label="Archivio dei provini">
        <header class="desk-heading"><div><p>NERONE · Area di produzione</p><h1>Provini.</h1></div><a href="{{ route('home') }}#progetto">Rivedi il progetto</a></header>
        @if(session('saved'))<p class="desk-notice" role="status">{{ session('saved') }}</p>@endif
        <form class="desk-search" method="get" action="{{ route('casting.index') }}" role="search" aria-label="Cerca nei provini">
            @if(request('status'))<input type="hidden" name="status" value="{{ request('status') }}">@endif
            <label for="search">Cerca un candidato<input id="search" name="q" value="{{ request('q') }}" placeholder="Nome, cognome o città" maxlength="100"></label>
            <label for="discipline-filter">Disciplina<select id="discipline-filter" name="discipline"><option value="">Tutte le discipline</option>@foreach($disciplines as $discipline)<option @selected(request('discipline')===$discipline)>{{ $discipline }}</option>@endforeach</select></label>
            <button class="button primary" type="submit">Cerca</button>
        </form>
        <div class="desk-results-heading"><h2>{{ request('status') === 'Archiviato' ? 'Archiviati' : (request('status') ?: 'Tutti i provini') }}</h2>@if(request()->hasAny(['q','discipline']))<a href="{{ route('casting.index',array_filter(['status'=>request('status')])) }}">Azzera ricerca</a>@else<p>Più recenti</p>@endif</div>
        @if($auditions->isNotEmpty())
            <table class="candidate-table"><thead><tr><th>Candidato</th><th>Disciplina</th><th>Città</th><th>Selezione</th><th>Materiale</th><th>Visione</th></tr></thead><tbody>
            @foreach($auditions as $audition)<tr class="candidate"><td><a class="candidate-name" href="{{ route('casting.show',$audition) }}">{{ $audition->first_name }} {{ $audition->last_name }}</a><p class="candidate-date">{{ $audition->created_at->format('d/m/Y') }}</p></td><td data-label="Disciplina">{{ $audition->discipline }}</td><td data-label="Città">{{ $audition->city }}</td><td data-label="Selezione"><span class="status-text">{{ $audition->status }}</span></td><td data-label="Materiale">{{ $audition->video_path ? 'Video disponibile' : 'Video non presente' }}</td><td><a class="open-audition" href="{{ route('casting.show',$audition) }}">Apri il provino<span class="sr-only"> di {{ $audition->first_name }} {{ $audition->last_name }}</span></a></td></tr>@endforeach
            </tbody></table>
        @else
            <div class="casting-empty"><h2>{{ request()->hasAny(['q','discipline','status']) ? 'Nessun provino corrisponde alla ricerca.' : 'Ancora nessun provino.' }}</h2><p>{{ request()->hasAny(['q','discipline','status']) ? 'Scegli un altro stato della selezione o modifica la ricerca.' : 'Le candidature inviate dal modulo compaiono qui, insieme al video e ai contatti del candidato.' }}</p><a href="{{ request()->hasAny(['q','discipline','status']) ? route('casting.index') : route('audition.apply') }}">{{ request()->hasAny(['q','discipline','status']) ? 'Mostra tutti i provini' : 'Apri il modulo candidature' }}</a></div>
        @endif
        @if($auditions->hasPages())<nav class="pagination" aria-label="Pagine dei provini">@if($auditions->previousPageUrl())<a href="{{ $auditions->previousPageUrl() }}">Pagina precedente</a>@endif @if($auditions->nextPageUrl())<a href="{{ $auditions->nextPageUrl() }}">Pagina successiva</a>@endif</nav>@endif
    </section>
</div>
@endsection

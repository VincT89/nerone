@extends('layout')
@section('title',$audition->first_name.' '.$audition->last_name)
@section('body-class','casting-page casting-profile')
@section('casting-nav','yes')
@section('content')
<div class="production-shell dossier-shell">
    @include('casting.navigation')
    @include('candidate.errors')
    <div class="dossier-cover"><a class="dossier-folder-tab" href="{{ route('casting.index') }}"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m14 6-6 6 6 6"/></svg>Candidature</a>@if(session('saved'))<p class="dossier-save-note" role="status">{{ session('saved') }}</p>@endif</div>
    <article class="candidate-dossier" aria-labelledby="candidate-name">
        <div class="dossier-split">
            <div class="dossier-mainpane" tabindex="0" aria-label="Profilo e video del candidato">
                <section class="dossier-profile" id="profilo" aria-labelledby="candidate-name">
                    <figure class="dossier-photo">@if($audition->photo_path)<img src="{{ route('material.photo',$audition) }}" alt="Foto di {{ $audition->first_name }} {{ $audition->last_name }}">@else<div>Foto non allegata</div>@endif</figure>
                    <div class="dossier-identity"><h1 id="candidate-name">{{ $audition->first_name }} {{ $audition->last_name }}</h1><dl class="dossier-data"><div><dt>Ruolo</dt><dd>{{ \App\Http\Controllers\AuditionController::roleLabels()[$audition->discipline]??$audition->discipline }}</dd></div><div><dt>Città / Paese di residenza</dt><dd>{{ $audition->city }}</dd></div><div><dt>Email</dt><dd><a href="mailto:{{ $audition->email }}">{{ $audition->email }}</a></dd></div><div><dt>Telefono</dt><dd><a href="tel:{{ preg_replace('/[^+0-9]/','',$audition->phone) }}">{{ $audition->phone }}</a></dd></div><div><dt>Ricevuta il</dt><dd>{{ $audition->created_at->timezone('Europe/Rome')->format('d/m/Y · H:i') }}</dd></div><div><dt>Selezione</dt><dd>{{ $audition->selection?->title }}</dd></div></dl></div>
                </section>
                @if($audition->application_details)
                    @include('casting.dossier-application-details')
                @else
                    <details class="dossier-summary"><summary>Presentazione</summary><p>{{ $audition->presentation?:'Nessuna presentazione aggiunta.' }}</p>@if($audition->notes)<p>Annotazioni precedenti: {{ $audition->notes }}</p>@endif</details>
                @endif
                @include('casting.dossier-videos')
                <div class="dossier-bottom-actions">
                    <a href="{{ route('casting.index') }}">Torna alle candidature</a>
                    @can('manage-casting')<a href="{{ route('casting.confirm-delete',$audition) }}">Elimina candidatura</a>@endcan
                </div>
            </div>
            <aside class="dossier-side" tabindex="0" aria-label="Stato, voti e commenti della produzione">
                <form class="dossier-status" action="{{ route('casting.update',$audition) }}" method="post">@csrf @method('PATCH')<label for="dossier-status">Stato<select id="dossier-status" name="status">@foreach($statuses as $status)<option @selected(old('status',$audition->status)===$status)>{{ $status }}</option>@endforeach</select></label><button class="button primary">Salva stato</button></form>
                @include('casting.dossier-votes')
                @include('casting.dossier-comments')
            </aside>
        </div>
    </article>
</div>
@endsection

@extends('layout')
@section('title',$audition->first_name.' '.$audition->last_name)
@section('body-class','casting-page casting-profile')
@section('casting-nav','yes')
@section('content')
<div class="casting-workflow">
    @include('casting.navigation',['currentStatus'=>$audition->status])
    <section class="casting-desk candidate-desk">
        <div class="viewing-top"><a href="{{ route('casting.index') }}">Tutti i provini</a><p>Ricevuto il {{ $audition->created_at->format('d/m/Y') }}</p></div>
        <header class="viewing-heading"><h1>{{ $audition->first_name }} {{ $audition->last_name }}</h1><p>{{ $audition->discipline }} · {{ $audition->city }}</p></header>
        <div class="viewing-workspace">
            <div class="viewing-material">
                @if($audition->video_path)<video class="audition-player" controls playsinline preload="metadata" aria-label="Video del provino di {{ $audition->first_name }}"><source src="{{ route('casting.video',$audition) }}" type="{{ $audition->video_mime }}">Il browser non supporta questo video.</video>@else<div class="video-empty"><h2>Video non presente</h2><p>Puoi associare un video alla candidatura.</p></div>@endif
                <details class="attach-video"><summary>{{ $audition->video_path ? 'Sostituisci il video' : 'Aggiungi un video' }}</summary><form method="post" action="{{ route('casting.attach',$audition) }}" enctype="multipart/form-data">@csrf<label>MP4, MOV o WebM · massimo 50 MB<input type="file" name="video" accept=".mp4,.mov,.webm" required></label><button class="button primary">Associa il video</button></form></details>
                <section class="candidate-story"><h2>La presentazione</h2><p>{{ $audition->presentation ?: 'Il candidato non ha aggiunto una presentazione.' }}</p><dl><div><dt>Email</dt><dd><a href="mailto:{{ $audition->email }}">{{ $audition->email }}</a></dd></div><div><dt>Telefono</dt><dd>{{ $audition->phone }}</dd></div></dl></section>
            </div>
            <aside class="evaluation-panel">
                <form method="post" action="{{ route('casting.update',$audition) }}" data-evaluation-form>@csrf @method('PATCH')<h2>La valutazione.</h2><p>Note riservate alla produzione.</p>
                    @if(session('saved'))<div class="saved-message" role="status">{{ session('saved') }}</div>@endif
                    @if($errors->any())<div class="form-errors" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
                    <label for="candidate-status">Stato della candidatura</label><select id="candidate-status" name="status">@foreach($statuses as $status)<option @selected(old('status',$audition->status)===$status)>{{ $status }}</option>@endforeach</select>
                    <label for="candidate-notes">Note riservate</label><textarea id="candidate-notes" name="notes" rows="9" maxlength="10000" placeholder="Interpretazione, presenza, aspetti da approfondire…">{{ old('notes',$audition->notes) }}</textarea>
                    <p class="evaluation-state" data-evaluation-state role="status" aria-live="polite"></p>
                    <button class="button primary">Salva la valutazione</button><p class="muted">Le note non vengono inviate al candidato.</p>
                </form>
                <div class="delete-candidate"><a href="{{ route('casting.confirm-delete',$audition) }}">Elimina candidatura</a></div>
            </aside>
        </div>
    </section>
</div>
@endsection

@extends('layout')
@section('title','Candidatura ricevuta')
@section('content')
<section class="quiet-page">
    <h1>La tua candidatura<br>è registrata.</h1>
    <p>Grazie per la candidatura. La produzione ha ricevuto la tua scheda e gli allegati.</p>
    @if($videoQueued)<p>Il self-tape è in elaborazione. Puoi chiudere questa pagina.</p>@endif
    <p>Se sarai selezionato/a, la produzione ti contatterà all’indirizzo email indicato nel modulo per il provino dal vivo. Le audizioni in presenza si terranno a {{ $auditionCity }} a {{ $auditionMonth }}. La produzione comunicherà direttamente date e luogo.</p>
    <dl class="application-receipt"><div><dt>Riferimento candidatura</dt><dd>{{ $reference }}</dd></div><div><dt>Ricevuta il</dt><dd><time datetime="{{ $submittedAt->toIso8601String() }}">{{ $submittedAt->format('d/m/Y · H:i:s') }}</time> (ora italiana)</dd></div></dl>
    <a class="button primary" href="{{ route('home') }}#progetto">Torna al progetto</a>
</section>
@endsection

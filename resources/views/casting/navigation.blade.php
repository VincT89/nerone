<aside class="casting-sidebar" aria-label="Organizzazione dei provini">
    <p class="sidebar-title">La selezione</p>
    <nav aria-label="Stato delle candidature">
        <a href="{{ route('casting.index') }}" @if(empty($currentStatus)) aria-current="page" @endif>Tutti i provini</a>
        @foreach($statuses as $selectionStatus)<a href="{{ route('casting.index',['status'=>$selectionStatus]) }}" @if(($currentStatus ?? null)===$selectionStatus) aria-current="page" @endif>{{ $selectionStatus === 'Archiviato' ? 'Archiviati' : $selectionStatus }}</a>@endforeach
    </nav>
    <a class="sidebar-public-link" href="{{ route('audition.apply') }}">Modulo candidature</a>
</aside>

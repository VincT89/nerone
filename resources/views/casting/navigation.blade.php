<nav class="production-nav" aria-label="Sezioni della produzione">
    <a href="{{ route('casting.index') }}" @if(request()->routeIs('casting.index','casting.show')) aria-current="page" @endif><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="4" width="18" height="16"/><path d="M3 9h18M9 9v11"/></svg>Candidature</a>
    @can('manage-casting')
        <a href="{{ route('casting.settings') }}" @if(request()->routeIs('casting.settings')) aria-current="page" @endif><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="16"/><path d="M7 2v6M17 2v6M3 11h18"/></svg>Apertura audizioni</a>
        <a href="{{ route('casting.team') }}" @if(request()->routeIs('casting.team')) aria-current="page" @endif><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="9" cy="7" r="3"/><path d="M3 21v-3a6 6 0 0 1 12 0v3M16 4a3 3 0 0 1 0 6M18 13a5 5 0 0 1 3 5v3"/></svg>Collaboratori</a>
    @endcan
    <span class="production-identity">{{ auth()->user()->name }}</span>
</nav>

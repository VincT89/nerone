<!doctype html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <meta name="description" content="NERONE. L’immaginario dell’antica Roma incontra un linguaggio scenico contemporaneo. Scopri il progetto e presenta il tuo provino.">
    <title>@yield('title', 'Il progetto') · NERONE</title>
    <link rel="icon" href="data:,">
    <link rel="stylesheet" href="{{ asset('assets/site.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/project.css') }}">
    <script src="{{ asset('assets/site.js') }}" defer></script>
</head>
<body class="@yield('body-class')">
@yield('entry-film')
<div class="site-shell" id="site-shell">
    <a class="skip-link" href="#main">Vai al contenuto</a>
    <header class="site-header">
        <nav aria-label="Navigazione principale">
            @hasSection('casting-nav')
                <a class="nav-context" href="{{ route('casting.index') }}">Sala casting</a>
                <div class="nav-links"><a href="{{ route('home') }}#progetto">Il progetto</a><form method="post" action="{{ route('casting.logout') }}">@csrf<button class="text-button" type="submit">Esci</button></form></div>
            @else
                <a class="nav-context" href="{{ route('home') }}#progetto">Il progetto</a>
                <div class="nav-links"><a class="nav-casting" href="{{ route('casting.index') }}">Area casting</a><a class="nav-apply" href="{{ route('audition.apply') }}">Candidati</a></div>
            @endif
        </nav>
    </header>
    <main id="main">@yield('content')</main>
    <footer class="site-footer">
        @hasSection('casting-nav')
            <p class="operator-footer">Materiale riservato alla produzione</p>
        @else
            <div><a class="footer-brand" href="{{ route('home') }}#progetto">NERONE</a><p>Progetto visivo · Laccioland Studio</p></div>
        @endif
        <nav aria-label="Informazioni legali">
            @hasSection('entry-film')<button class="footer-film" type="button" data-open-trailer>Rivedi il trailer</button>@endif
            <a href="{{ route('privacy') }}">Privacy</a><a href="{{ route('cookies') }}">Cookie</a>
            @hasSection('casting-nav')@else<a href="{{ route('casting.index') }}">Area casting</a>@endif
        </nav>
    </footer>
</div>
</body>
</html>

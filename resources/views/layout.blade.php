<!doctype html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="NERO! Nuovo musical in preparazione a Roma. Informazioni su audizioni, profili ricercati e candidature online.">
    <title>@yield('title', 'Il progetto') · {{ config('casting.project.title') }}</title>
    <link rel="icon" href="data:,">
    <link rel="preload" href="{{ asset('assets/nero/progetto-neue.woff2') }}?v=4" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="{{ asset('assets/site.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/project.css') }}?v={{ filemtime(public_path('assets/project.css')) }}">
    <link rel="stylesheet" href="{{ asset('assets/cinema.css') }}?v={{ filemtime(public_path('assets/cinema.css')) }}">
    <link rel="stylesheet" href="{{ asset('assets/candidates.css') }}?v={{ filemtime(public_path('assets/candidates.css')) }}">
    <link rel="stylesheet" href="{{ asset('assets/landing.css') }}?v={{ filemtime(public_path('assets/landing.css')) }}">
    <link rel="stylesheet" href="{{ asset('assets/production.css') }}?v={{ filemtime(public_path('assets/production.css')) }}">
    <link rel="stylesheet" href="{{ asset('assets/nero.css') }}?v={{ filemtime(public_path('assets/nero.css')) }}">
    <link rel="stylesheet" href="{{ asset('assets/nero-actions.css') }}?v={{ filemtime(public_path('assets/nero-actions.css')) }}">
    <script src="{{ asset('assets/site.js') }}?v={{ filemtime(public_path('assets/site.js')) }}" defer></script>
    <script src="{{ asset('assets/reviews.js') }}?v={{ filemtime(public_path('assets/reviews.js')) }}" defer></script>
</head>
<body id="top" class="@yield('body-class')">
<x-photo-tone />
@yield('entry-film')
<div class="site-shell" id="site-shell">
    <a class="skip-link" href="#main">Vai al contenuto</a>
    <header class="site-header">
        <nav aria-label="Navigazione principale">
            @hasSection('casting-nav')
                <a class="nav-context" href="{{ route('casting.index') }}">Produzione</a><div class="nav-links"><form method="post" action="{{ route('casting.logout') }}">@csrf<button class="text-button" type="submit">Esci</button></form></div>
            @else
                @yield('public-nav-start')
                @unless(request()->routeIs('audition.apply'))
                    <div class="nav-links"><a class="nav-apply" href="{{ route('audition.apply') }}">Candidati</a></div>
                @endunless
            @endif
        </nav>
    </header>
    <main id="main">@yield('content')</main>
    <footer class="site-footer footer-layout{{ $__env->hasSection('entry-film')?' has-film':'' }}{{ $__env->hasSection('casting-nav')?' is-production':'' }}">
        @hasSection('casting-nav')<p class="operator-footer">Materiale riservato alla produzione</p>@else<p>{{ config('casting.project.title') }}</p>@endif
        <nav aria-label="Informazioni e accesso">
            @hasSection('entry-film')<button class="footer-film" type="button" data-open-trailer>Rivedi il video</button>@endif
            <div class="footer-legal">
                <a href="{{ route('privacy') }}">Privacy</a>
                <a href="{{ route('cookies') }}">Cookie</a>
                @if(!$__env->hasSection('casting-nav'))
                    <a class="footer-contact" href="mailto:{{ config('casting.project.contact_email') }}">{{ config('casting.project.contact_email') }}</a>
                @endif
            </div>
            @if(!$__env->hasSection('casting-nav'))<a class="reserved-area" href="{{ route('login') }}" target="_blank" rel="noopener">Area riservata</a>@endif
            <a href="#top" class="footer-top footer-top-control" aria-label="Torna all’inizio della pagina" title="Torna su"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="m6 11 6-6 6 6M12 5v14"/></svg></a>
        </nav>
    </footer>
</div>
<a class="back-to-top back-to-top-control" href="#top" data-back-top hidden aria-label="Torna all’inizio della pagina" title="Torna su"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="m6 11 6-6 6 6M12 5v14"/></svg></a>
</body>
</html>

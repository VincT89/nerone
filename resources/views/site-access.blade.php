<!doctype html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>Accesso riservato · {{ config('casting.project.title') }}</title>
    <link rel="icon" href="data:,">
    <link rel="preload" href="{{ asset('assets/nero/progetto-neue.woff2') }}?v=4" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="{{ asset('assets/site-access.css') }}?v={{ filemtime(public_path('assets/site-access.css')) }}">
    <script src="{{ asset('assets/site.js') }}?v={{ filemtime(public_path('assets/site.js')) }}" defer></script>
</head>
<body>
    <main class="site-access-shell">
        <h1>{{ config('casting.project.title') }}</h1>
        <form method="post" action="{{ route('site.access.enter') }}" data-site-access-form>
            @csrf
            <x-password-field id="site-password" required maxlength="256" :aria-invalid="$errors->has('password') ? 'true' : 'false'" :aria-describedby="$errors->has('password') ? 'site-password-error' : null" />
            @error('password')
                <p class="site-access-error" id="site-password-error" role="alert">{{ $message }}</p>
            @enderror
            <button class="site-access-submit" type="submit">Entra</button>
        </form>
        <nav aria-label="Informative">
            <a href="{{ route('privacy') }}">Privacy</a>
            <a href="{{ route('cookies') }}">Cookie</a>
        </nav>
    </main>
</body>
</html>

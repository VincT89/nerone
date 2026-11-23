<?php

namespace App\Http\Middleware;

use App\Services\SiteAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSiteAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $accessible = app(SiteAccess::class)->allows($request);
        $request->attributes->set('siteAccessible', $accessible);

        if ($accessible || !$request->routeIs('home', 'audition.*')) {
            return $next($request);
        }

        $reading = $request->isMethod('GET') || $request->isMethod('HEAD');
        $response = !$reading && $request->expectsJson()
            ? response()->json(['message'=>'Inserisci la password di accesso al sito.'], 403)
            : response()->view('site-access', [], $reading ? 200 : 403);

        return $response->header('Cache-Control', 'no-store')
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }
}

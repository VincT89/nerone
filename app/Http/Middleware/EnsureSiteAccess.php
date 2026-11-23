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
        $access = app(SiteAccess::class);
        $protected = $request->routeIs('home', 'audition.*');
        $reading = $request->isMethod('GET') || $request->isMethod('HEAD');
        if ($reading && ($protected || $request->routeIs('privacy', 'cookies'))) {
            $access->checkEntry($request);
        }

        $accessible = $access->allows($request);
        $request->attributes->set('siteAccessible', $accessible);
        $request->attributes->set('siteAccessProtected', $protected && $access->isEnabled());

        if ($accessible || !$protected) {
            $response = $next($request);
            if ($protected && $access->isEnabled()) {
                $response->headers->set('Cache-Control', 'no-store');
                $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
                if (!$reading && $response->isRedirection()) {
                    $access->continueAfterRedirect($request, $response->headers->get('Location', ''));
                }
            }

            return $response;
        }

        $response = !$reading && $request->expectsJson()
            ? response()->json(['message'=>'Inserisci la password di accesso al sito.'], 403)
            : response()->view('site-access', [], $reading ? 200 : 403);

        return $response->header('Cache-Control', 'no-store')
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }
}

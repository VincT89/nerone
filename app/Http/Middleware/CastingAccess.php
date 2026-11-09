<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
class CastingAccess {
    public function handle(Request $request, Closure $next) {
        abort_if(config('casting.local_access_only') && !in_array($request->ip(),['127.0.0.1','::1'],true),403);
        if (!Auth::check()) { return redirect()->route('login'); }
        /** L'autenticazione identifica l'utente; il ruolo autorizza l'accesso al materiale dei candidati. */
        abort_unless(Auth::user()->is_casting_operator,403);
        $response=$next($request);
        $response->headers->set('Cache-Control','private, no-store');
        return $response;
    }
}

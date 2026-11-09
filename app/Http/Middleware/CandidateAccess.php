<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
class CandidateAccess {
    public function handle(Request $request,Closure $next){
        if(!Auth::guard('candidate')->check()) return redirect()->route('login');
        $response=$next($request);$response->headers->set('Cache-Control','private, no-store');return $response;
    }
}

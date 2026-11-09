<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
class CastingAuthController extends Controller {
    public function show() { return redirect()->route('login'); }
    public function login(Request $request) {
        /** L'installazione locale rimane isolata finché non vengono definite credenziali e hosting di produzione. */
        abort_if(config('casting.local_access_only') && !in_array($request->ip(),['127.0.0.1','::1'],true),403);
        $data=$request->validate(['email'=>['required','email','max:254'],'password'=>['required','string','max:200']]);
        if(!Auth::attempt([...$data,'is_casting_operator'=>true])) { throw ValidationException::withMessages(['email'=>'Email o password non corrette.']); }
        $request->session()->regenerate(); return redirect()->route('casting.index');
    }
    public function logout(Request $request) {
        Auth::logout(); $request->session()->invalidate(); $request->session()->regenerateToken();
        return redirect()->route('home');
    }
}

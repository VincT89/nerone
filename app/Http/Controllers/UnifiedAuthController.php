<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
class UnifiedAuthController extends Controller {
    public function show(){if(Auth::check() && Auth::user()->is_casting_operator)return redirect()->route('casting.index');return view('login');}
    public function enter(Request $request){
        $data=$request->validate(['email'=>['required','email','max:254'],'password'=>['required','string','max:200']]);$data['email']=mb_strtolower(trim($data['email']));
        $localAllowed=!config('casting.local_access_only') || in_array($request->ip(),['127.0.0.1','::1'],true);
        /** Il login autorizza esclusivamente gli account della produzione. */
        if($localAllowed && Auth::guard('web')->attempt([...$data,'is_casting_operator'=>true])){$request->session()->regenerate();return redirect()->route('casting.index');}
        throw ValidationException::withMessages(['email'=>'Email o password non corrette.']);
    }
}

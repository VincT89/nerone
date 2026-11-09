<?php
namespace App\Http\Controllers;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
class ProductionTeamController extends Controller {
    public function index(){abort_unless(auth()->user()->is_casting_admin,403);return view('casting.team',['operators'=>User::where('is_casting_operator',true)->orderBy('name')->get()]);}
    public function store(Request $request){
        abort_unless($request->user()->is_casting_admin,403);
        $request->merge(['email'=>mb_strtolower(trim((string)$request->input('email')))]);
        $data=$request->validate(['name'=>['required','string','max:100'],'email'=>['required','email','max:254','unique:users,email'],'password'=>['required','string','confirmed','max:72',Password::min(12)->letters()->numbers()]]);
        $user=new User;$user->forceFill([...$data,'is_casting_operator'=>true,'is_casting_admin'=>false])->save();
        return back()->with('saved','Collaboratore aggiunto. Le credenziali vanno comunicate direttamente alla persona.');
    }
}

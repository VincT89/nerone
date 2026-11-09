<?php
namespace App\Http\Controllers;
use App\Models\{Audition,Candidate,CandidateAccessToken,MaterialRequest};
use App\Services\CandidatePost;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth,DB,Storage};
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Rule;
class CandidateController extends Controller {
    public function login(){return redirect()->route('login');}
    public function enter(Request $request){
        $data=$request->validate(['email'=>['required','email'],'password'=>['required','string']]);$data['email']=mb_strtolower(trim($data['email']));
        if(!Auth::guard('candidate')->attempt($data)) return back()->withErrors(['email'=>'Email o password non corrette.'])->onlyInput('email');
        $request->session()->regenerate();return redirect()->route('candidate.home');
    }
    public function activate(){return response()->view('candidate.activate')->header('Cache-Control','no-store')->header('Referrer-Policy','no-referrer');}
    public function setPassword(Request $request){
        $data=$request->validate(['token'=>['required','string','size:64','regex:/^[a-f0-9]+$/D'],'password'=>['required','string','max:72','confirmed',function($attribute,$value,$fail){if(strlen($value)>72)$fail('La password supera la lunghezza supportata.');},Password::min(12)->letters()->numbers()]]);
        $candidate=DB::transaction(function()use($data){
            $token=CandidateAccessToken::where('token_hash',hash('sha256',$data['token']))->lockForUpdate()->first();
            if(!$token || $token->consumed_at || $token->expires_at->isPast()) return null;
            $candidate=$token->candidate;$candidate->forceFill(['password'=>$data['password'],'email_verified_at'=>now(),'remember_token'=>bin2hex(random_bytes(30))])->save();
            CandidateAccessToken::where('candidate_id',$candidate->id)->whereNull('consumed_at')->update(['consumed_at'=>now()]);return $candidate;
        });
        if(!$candidate) return back()->withErrors(['token'=>'Il collegamento è scaduto o è già stato utilizzato. Richiedi un nuovo accesso.']);
        Auth::guard('web')->logout();Auth::guard('candidate')->login($candidate);$request->session()->regenerate();return redirect()->route('candidate.home');
    }
    public function recover(Request $request,CandidatePost $post){
        $data=$request->validate(['email'=>['required','email','max:254']]);
        $candidate=Candidate::where('email',mb_strtolower(trim($data['email'])))->first();
        if($candidate?->audition && !$candidate->updated_at->gt(now()->subMinute())){$post->invite($candidate->audition);$candidate->touch();}
        return back()->with('sent','Se l’indirizzo corrisponde a una candidatura, verranno predisposte le istruzioni per accedere.');
    }
    public function home(){
        request()->validate(['candidatura'=>['nullable','integer']]);
        $applications=Auth::guard('candidate')->user()->auditions()->with('selection')->latest('id')->get();
        $audition=request('candidatura')?$applications->firstWhere('id',(int)request('candidatura')):$applications->first();abort_unless($audition,404);
        return view('candidate.home',['audition'=>$audition->load(['phase','materialRequests.phase']),'applications'=>$applications]);
    }
    public function reply(Request $request,MaterialRequest $materialRequest){
        abort_unless($materialRequest->audition->candidate_id===Auth::guard('candidate')->id(),404);
        abort_if($materialRequest->submitted_at,409,'La risposta è già stata inviata.');
        abort_unless($materialRequest->phase_id===$materialRequest->audition->phase_id && ($materialRequest->phase?->acceptsMaterials()??false),422,'Il periodo di invio per questa fase non è aperto.');
        $rules=['answer'=>['nullable','string','max:10000']];
        if($materialRequest->kind==='text') $rules['answer']=['required','string','max:10000'];
        else $rules['material']=match($materialRequest->kind){
            'video'=>['required','file','mimetypes:video/mp4,video/quicktime,video/webm','extensions:mp4,mov,webm','max:51200'],
            'photo'=>['required','image','mimes:jpg,jpeg,png,webp','max:5120',Rule::dimensions()->maxWidth(5000)->maxHeight(5000)],
            default=>['required','file','mimetypes:application/pdf','extensions:pdf','max:10240'],
        };
        $data=$request->validate($rules);$path=null;
        if($request->hasFile('material')) $path=$request->file('material')->store('candidate-materials','local');
        try{
            DB::transaction(function()use($materialRequest,$request,$data,$path){
                $locked=MaterialRequest::whereKey($materialRequest->id)->lockForUpdate()->firstOrFail();abort_if($locked->submitted_at,409);
                $locked->update(['answer'=>$data['answer']??null,'file_path'=>$path,'file_mime'=>$path?$request->file('material')->getMimeType():null,'submitted_at'=>now()]);
            });
        }catch(\Throwable $e){if($path)Storage::disk('local')->delete($path);throw $e;}
        return back()->with('saved','Materiale inviato alla produzione.');
    }
    public function logout(Request $request){Auth::guard('candidate')->logout();$request->session()->migrate(true);$request->session()->regenerateToken();return redirect()->route('candidate.login');}
}

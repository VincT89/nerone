<?php
namespace App\Http\Controllers;
use App\Models\{Audition,CandidateMessage,MaterialRequest};
use App\Services\CandidatePost;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
class CastingCommunicationController extends Controller {
    public function inbox(CandidatePost $post){abort_unless($post->localAvailable() && in_array(request()->ip(),['127.0.0.1','::1'],true),404);return view('casting.mail',['messages'=>CandidateMessage::with(['candidate','audition'])->latest('id')->paginate(12)]);}
    public function invite(Audition $audition,CandidatePost $post){return back()->with('saved',$post->invite($audition)?'Accesso predisposto nella posta locale.':'Invio degli accessi non ancora configurato.');}
    public function store(Request $request,Audition $audition,CandidatePost $post){
        $data=$request->validate(['title'=>['required','string','max:160'],'kind'=>['required',Rule::in(array_keys(MaterialRequest::KINDS))],'instructions'=>['required','string','max:5000']]);
        $post->account($audition);$material=$audition->materialRequests()->create([...$data,'phase_id'=>$audition->phase_id]);
        $post->message($audition,'NERONE · '.$material->title,['text'=>$material->instructions,'url'=>rtrim(config('app.url'),'/').route('candidate.home',['candidatura'=>$audition->id],false),'action'=>'Apri la tua area personale']);
        return back()->with('saved','Richiesta aggiunta all’area del candidato. Comunicazione disponibile nella posta locale.');
    }
}

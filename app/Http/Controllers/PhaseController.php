<?php
namespace App\Http\Controllers;
use App\Models\{Audition,PhaseAdmission,Selection,SelectionPhase};
use App\Services\CandidatePost;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
class PhaseController extends Controller {
    private function data(Request $request): array {
        $data=$request->validate(['title'=>['required','string','max:120'],'starts_at'=>['nullable','date_format:Y-m-d\TH:i'],'ends_at'=>['nullable','date_format:Y-m-d\TH:i']]);
        foreach(['starts_at','ends_at'] as $key)$data[$key]=empty($data[$key])?null:Carbon::createFromFormat('!Y-m-d\TH:i',$data[$key],'Europe/Rome')->utc();
        if($data['starts_at'] && $data['ends_at'] && $data['ends_at']->lt($data['starts_at']))throw ValidationException::withMessages(['ends_at'=>'La fine della fase deve seguire il suo inizio.']);
        return $data;
    }
    public function store(Request $request,Selection $selection){
        $data=$this->data($request);DB::transaction(function()use($selection,$data){$locked=Selection::whereKey($selection->id)->lockForUpdate()->firstOrFail();$locked->phases()->create([...$data,'position'=>(int)$locked->phases()->max('position')+1]);});return back()->with('saved','Fase aggiunta.');
    }
    public function update(Request $request,SelectionPhase $phase){$phase->update($this->data($request));return back()->with('saved','Periodo della fase aggiornato.');}
    public function advance(Request $request,Audition $audition,CandidatePost $post){
        $data=$request->validate(['from_phase_id'=>['required','integer']]);
        $next=DB::transaction(function()use($audition,$data){
            $locked=Audition::whereKey($audition->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->phase_id===(int)$data['from_phase_id'],409,'La fase è già cambiata. Aggiorna la scheda.');
            $current=$locked->phase;abort_unless($current,422,'Configura prima la fase iniziale.');
            $next=SelectionPhase::where('selection_id',$locked->selection_id)->where('position','>',$current->position)->orderBy('position')->first();abort_unless($next,422,'Non ci sono altre fasi configurate.');
            $locked->update(['phase_id'=>$next->id]);PhaseAdmission::create(['audition_id'=>$locked->id,'phase_id'=>$next->id,'operator_id'=>auth()->id()]);return $next;
        });
        $post->message($audition,'NERONE · Passaggio di fase',['text'=>'La produzione ti ha ammesso alla fase: '.$next->title.'. Accedi alla tua area per conoscere il periodo e i materiali richiesti.','url'=>rtrim(config('app.url'),'/').route('candidate.home',['candidatura'=>$audition->id],false),'action'=>'Apri la tua area']);
        return back()->with('saved','Candidato ammesso alla fase successiva. Comunicazione predisposta nella posta locale.');
    }
}

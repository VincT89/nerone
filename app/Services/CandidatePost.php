<?php
namespace App\Services;
use App\Models\{Audition,Candidate,CandidateAccessToken,CandidateMessage};
use Illuminate\Support\Facades\DB;
class CandidatePost {
    public function localAvailable(): bool {return config('casting.local_access_only') && in_array(parse_url(config('app.url'),PHP_URL_HOST),['127.0.0.1','localhost','::1'],true);}
    public function account(Audition $audition): Candidate {
        if($audition->candidate) return $audition->candidate;
        $candidate=Candidate::firstOrCreate(['email'=>mb_strtolower(trim($audition->email))]);
        $audition->update(['candidate_id'=>$candidate->id]);return $candidate;
    }
    public function invite(Audition $audition): bool {
        if(!$this->localAvailable()) return false;
        $candidate=$this->account($audition);
        return DB::transaction(function()use($candidate,$audition){
            $raw=bin2hex(random_bytes(32));
            CandidateAccessToken::create(['candidate_id'=>$candidate->id,'token_hash'=>hash('sha256',$raw),'expires_at'=>now()->addHours(48)]);
            /** Il frammento non raggiunge i log HTTP; il token viene inviato al server solo nel modulo protetto da CSRF. */
            $url=rtrim(config('app.url'),'/').route('candidate.activate',[],false).'#token='.$raw;
            $this->message($audition,'Il tuo accesso all’area NERONE',['text'=>'La tua candidatura è stata registrata. Apri il collegamento per scegliere la password della tua area personale. Il collegamento vale 48 ore e può essere usato una sola volta.','url'=>$url,'action'=>'Attiva il tuo accesso']);
            return true;
        });
    }
    public function message(Audition $audition,string $subject,array $payload): void {
        CandidateMessage::create(['candidate_id'=>$this->account($audition)->id,'audition_id'=>$audition->id,'subject'=>$subject,'payload'=>$payload,'delivery'=>'local']);
    }
}

<?php
namespace App\Http\Controllers;
use App\Models\Audition;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
class AuditionController extends Controller {
    public const DISCIPLINES = ['Canto','Recitazione','Danza','Musica'];
    public const ROLES = ['Canto'=>'Cantante','Recitazione'=>'Attore / Attrice','Danza'=>'Ballerino / Ballerina','Musica'=>'Musicista'];
    public const STATUSES = ['Da vedere','In valutazione','Da richiamare','Convocato dal vivo','Nel cast','Archiviato'];
    public function apply(Request $request){return response()->view('apply',['roles'=>self::ROLES,'selection'=>\App\Models\Selection::with('phases')->latest('id')->first(),'captcha'=>app(\App\Services\LocalCaptcha::class)->issue()])->header('Cache-Control','no-store');}
    public function store(Request $request) {
        $selection=\App\Models\Selection::latest('id')->first();
        $data=$request->validate([
            'selection_id'=>['required','integer',Rule::in([$selection?->id])],
            'first_name'=>['required','string','max:80'],'last_name'=>['required','string','max:80'],'email'=>['required','email','max:254'],
            'phone'=>['required','string','max:40','regex:/^[0-9+() .\-]{5,40}$/'],'city'=>['required','string','max:100'],'discipline'=>['required',Rule::in(self::DISCIPLINES)],
            'presentation'=>['nullable','string','max:2000'],'photo'=>['nullable','image','mimes:jpg,jpeg,png,webp','max:5120',Rule::dimensions()->maxWidth(5000)->maxHeight(5000)],
            'videos'=>['required','array:0,1,2','size:3'],'videos.0'=>['required'],'videos.1'=>['required'],'videos.2'=>['required'],
            'videos.*'=>['required','file','mimetypes:video/mp4,video/quicktime,video/webm','extensions:mp4,mov,webm','max:102400'],
            'consent'=>['accepted'],'adult'=>['accepted'],'website'=>['nullable','max:0'],'captcha_id'=>['required','string','alpha_num:ascii','size:32'],'captcha_code'=>['required','string','regex:/^[0-9]{6}$/D'],
        ],['required'=>'Completa questo campo.','email'=>'Inserisci un indirizzo email valido.','accepted'=>'Conferma questa voce per proseguire.','photo.max'=>'La foto deve pesare al massimo 5 MB.','photo.image'=>'Scegli una foto JPG, PNG o WebP.','phone.regex'=>'Inserisci un numero di telefono valido.','videos.required'=>'Carica tutti e tre i video.','videos.size'=>'Sono richiesti esattamente tre video.','videos.*.required'=>'Carica questo video.','videos.*.max'=>'Il limite complessivo dei tre video è 100 MB.','videos.*.mimetypes'=>'Scegli un video MP4, MOV o WebM.','videos.*.extensions'=>'Scegli un video MP4, MOV o WebM.']);
        if(array_sum(array_map(fn($file)=>$file->getSize(),$request->file('videos')))>100*1024*1024)throw \Illuminate\Validation\ValidationException::withMessages(['videos'=>'I tre video insieme non possono superare 100 MB.']);
        if(!app(\App\Services\LocalCaptcha::class)->verify($data['captcha_id'],$data['captcha_code']))throw \Illuminate\Validation\ValidationException::withMessages(['captcha_code'=>'Il codice di verifica non è corretto o è scaduto. Inserisci il nuovo codice.']);
        $data['email']=mb_strtolower(trim($data['email']));
        $audition=app(\App\Services\AuditionUploadGuard::class)->run($request,function()use($request,$data){$paths=[];
        try {
            $audition=\Illuminate\Support\Facades\DB::transaction(function()use($request,$data,&$paths){
                $selection=\App\Models\Selection::whereKey($data['selection_id'])->lockForUpdate()->firstOrFail();
                if(!$selection->acceptsApplications())throw \Illuminate\Validation\ValidationException::withMessages(['selection_id'=>'Il periodo per le candidature non è aperto.']);
                /** Il blocco della selezione e il vincolo email rendono idempotente l'invio senza creare account. */
                $existing=Audition::where('email',$data['email'])->where('selection_id',$selection->id)->first();
                if($existing)return $existing;
                $photo=null;
                if($request->hasFile('photo')){$photo=$request->file('photo')->store('candidate-photos','local');$paths[]=$photo;}
                $audition=Audition::create([...collect($data)->only(['selection_id','first_name','last_name','email','phone','city','discipline','presentation'])->all(),'phase_id'=>$selection->phases()->first()?->id,'reference'=>(string)Str::uuid(),'photo_path'=>$photo,'photo_mime'=>$photo?$request->file('photo')->getMimeType():null,'consented_at'=>now(),'adult_confirmed_at'=>now(),'privacy_version'=>'2026-11-04']);
                foreach($request->file('videos') as $index=>$file){$path=$file->store('audition-videos','local');$paths[]=$path;$audition->videos()->create(['position'=>$index+1,'file_path'=>$path,'file_mime'=>$file->getMimeType(),'bytes'=>$file->getSize()]);}
                return $audition;
            });
        }catch(\Throwable $e){if($paths)Storage::disk('local')->delete($paths);throw $e;}
        return $audition;});
        return redirect()->route('audition.received',$audition->reference);
    }
    public function received(string $reference) {
        /** La conferma utilizza un riferimento casuale e non espone i dati del candidato. */
        abort_unless(Audition::where('reference',$reference)->exists(),404);
        return response()->view('received',compact('reference'))->header('Cache-Control','no-store');
    }
    public function index(Request $request) {
        $data=$request->validate(['q'=>['nullable','string','max:100'],'discipline'=>['nullable',Rule::in(self::DISCIPLINES)],'status'=>['nullable',Rule::in(self::STATUSES)],'received_from'=>['nullable','date_format:Y-m-d'],'received_to'=>['nullable','date_format:Y-m-d']]);
        if(!empty($data['received_from']) && !empty($data['received_to']) && $data['received_to']<$data['received_from'])throw \Illuminate\Validation\ValidationException::withMessages(['received_to'=>'La data finale deve seguire quella iniziale.']);
        $selection=\App\Models\Selection::latest('id')->first();
        $base=Audition::query()->where('selection_id',$selection?->id);
        /** Il giorno italiano viene convertito in un intervallo UTC, rispettando anche il cambio dell'ora. */
        $today=\Carbon\CarbonImmutable::now('Europe/Rome')->startOfDay();
        $todayCount=(clone $base)->where('created_at','>=',$today->utc())->where('created_at','<',$today->addDay()->utc())->count();
        $roleCounts=(clone $base)->selectRaw('discipline, count(*) as total')->groupBy('discipline')->pluck('total','discipline');
        $query=$base->with('votes')->withCount(['videos','comments']);
        if(!empty($data['received_from']))$query->where('created_at','>=',\Carbon\Carbon::parse($data['received_from'],'Europe/Rome')->startOfDay()->utc());
        if(!empty($data['received_to']))$query->where('created_at','<',\Carbon\Carbon::parse($data['received_to'],'Europe/Rome')->startOfDay()->addDay()->utc());
        if($search=$data['q']??null)$query->where(function($q)use($search){$q->whereLike('first_name',"%{$search}%")->orWhereLike('last_name',"%{$search}%")->orWhereLike('city',"%{$search}%");});
        foreach(['discipline','status'] as $field)if(!empty($data[$field]))$query->where($field,$data[$field]);
        return view('casting.index',['auditions'=>$query->latest('id')->paginate(30)->withQueryString(),'statuses'=>self::STATUSES,'roles'=>self::ROLES,'roleCounts'=>$roleCounts,'selection'=>$selection,'todayCount'=>$todayCount,'todayDate'=>$today->toDateString()]);
    }
    public function show(Audition $audition){return view('casting.show',['audition'=>$audition->load(['videos','votes.user']),'comments'=>$audition->comments()->with('user')->latest('id')->paginate(50),'operators'=>\App\Models\User::where('is_casting_operator',true)->orderBy('name')->get(),'voteLabels'=>AuditionReviewController::VOTES,'statuses'=>self::STATUSES]);}
    public function update(Request $request,Audition $audition){$audition->update($request->validate(['status'=>['required',Rule::in(self::STATUSES)]]));return back()->with('saved','Stato aggiornato.');}
    public function video(Audition $audition) {
        abort_unless($audition->video_path && Storage::disk('local')->exists($audition->video_path),404);
        /** Il video resta fuori dalla cartella pubblica e anche lo streaming richiede una sessione autorizzata. */
        $disk=Storage::disk('local'); $size=$disk->size($audition->video_path); $start=0; $end=$size-1; $status=200;
        if($range=request()->header('Range')) {
            if(!preg_match('/^bytes=(\d*)-(\d*)$/D',$range,$matches) || ($matches[1]==='' && $matches[2]==='')) { return response('',416,['Content-Range'=>"bytes */{$size}"]); }
            if($matches[1]==='') { $start=max(0,$size-(int)$matches[2]); }
            else { $start=(int)$matches[1]; if($matches[2]!=='') { $end=min($end,(int)$matches[2]); } }
            if($start>$end || $start>=$size) { return response('',416,['Content-Range'=>"bytes */{$size}"]); }
            $status=206;
        }
        $headers=['Content-Type'=>$audition->video_mime,'Cache-Control'=>'private, no-store','X-Content-Type-Options'=>'nosniff','Accept-Ranges'=>'bytes','Content-Length'=>(string)($end-$start+1)];
        if($status===206) { $headers['Content-Range']="bytes {$start}-{$end}/{$size}"; }
        return response()->stream(function() use($disk,$audition,$start,$end) {
            $stream=$disk->readStream($audition->video_path);
            if(!is_resource($stream)) { return; }
            try {
                fseek($stream,$start); $remaining=$end-$start+1;
                while($remaining>0 && !feof($stream) && !connection_aborted()) {
                    $chunk=fread($stream,min(65536,$remaining)); if($chunk===false || $chunk==='') { break; }
                    echo $chunk; $remaining-=strlen($chunk);
                }
            } finally { fclose($stream); }
        },$status,$headers);
    }
    public function confirmDelete(Audition $audition) { return view('casting.delete',compact('audition')); }
    public function destroy(Request $request,Audition $audition) {
        $request->validate(['confirmation'=>['required',Rule::in([$audition->first_name.' '.$audition->last_name])]],['confirmation.in'=>'Il nome non corrisponde. La candidatura non è stata eliminata.']);
        /** La cancellazione della scheda avviene soltanto dopo quella del materiale video privato. */
        if($audition->video_path && Storage::disk('local')->exists($audition->video_path)) {
            abort_unless(Storage::disk('local')->delete($audition->video_path),500,'Impossibile eliminare il video. La scheda è stata conservata.');
        }
        $paths=[...$audition->videos()->pluck('file_path')->all(),...$audition->materialRequests()->whereNotNull('file_path')->pluck('file_path')->all()];
        if($audition->photo_path) $paths[]=$audition->photo_path;
        foreach($paths as $privatePath){if(Storage::disk('local')->exists($privatePath))abort_unless(Storage::disk('local')->delete($privatePath),500,'Impossibile eliminare un allegato.');}
        $audition->delete();
        return redirect()->route('casting.index')->with('saved','Candidatura e video eliminati.');
    }
}


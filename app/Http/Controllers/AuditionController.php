<?php
namespace App\Http\Controllers;
use App\Models\Audition;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
class AuditionController extends Controller {
    public const DISCIPLINES = ['Canto','Recitazione','Danza','Musica'];
    public const STATUSES = ['Da vedere','In valutazione','Da richiamare','Nel cast','Archiviato'];
    public function store(Request $request) {
        $data=$request->validate([
            'first_name'=>['required','string','max:80'],'last_name'=>['required','string','max:80'],
            'email'=>['required','email','max:254'],'phone'=>['required','string','max:40','regex:/^[0-9+() .\-]{5,40}$/'],
            'city'=>['required','string','max:100'],'discipline'=>['required',Rule::in(self::DISCIPLINES)],
            'presentation'=>['nullable','string','max:2000'],
            'video'=>['required','file','mimetypes:video/mp4,video/quicktime,video/webm','extensions:mp4,mov,webm','max:51200'],
            'consent'=>['accepted'],'adult'=>['accepted'],'website'=>['nullable','max:0'],
        ],['required'=>'Completa questo campo.','email'=>'Inserisci un indirizzo email valido.','accepted'=>'Conferma questa voce per proseguire.','video.max'=>'Il video deve pesare al massimo 50 MB.','video.mimetypes'=>'Carica un video MP4, MOV o WebM.','video.extensions'=>'Usa un file MP4, MOV o WebM.','phone.regex'=>'Inserisci un numero di telefono valido.']);
        $path=$request->file('video')->store('auditions','local');
        try {
            $audition=Audition::create([...collect($data)->only(['first_name','last_name','email','phone','city','discipline','presentation'])->all(), 'reference'=>(string)Str::uuid(),'video_path'=>$path,'video_mime'=>$request->file('video')->getMimeType(),'consented_at'=>now(),'adult_confirmed_at'=>now(),'privacy_version'=>'2026-11-03']);
        } catch (\Throwable $e) { Storage::disk('local')->delete($path); throw $e; }
        return redirect()->route('audition.received',$audition->reference);
    }
    public function received(string $reference) {
        /** La conferma utilizza un riferimento casuale e non espone i dati del candidato. */
        abort_unless(Audition::where('reference',$reference)->exists(),404);
        return response()->view('received',compact('reference'))->header('Cache-Control','no-store');
    }
    public function index(Request $request) {
        $data=$request->validate(['q'=>['nullable','string','max:100'],'discipline'=>['nullable',Rule::in(self::DISCIPLINES)],'status'=>['nullable',Rule::in(self::STATUSES)]]);
        $query=Audition::query();
        if ($search=$data['q']??null) { $query->where(function($q) use($search) { $q->whereLike('first_name',"%{$search}%")->orWhereLike('last_name',"%{$search}%")->orWhereLike('city',"%{$search}%"); }); }
        foreach(['discipline','status'] as $field) { if(!empty($data[$field])) { $query->where($field,$data[$field]); } }
        return view('casting.index',['auditions'=>$query->latest('id')->paginate(12)->withQueryString(),'disciplines'=>self::DISCIPLINES,'statuses'=>self::STATUSES]);
    }
    public function show(Audition $audition) { return view('casting.show',['audition'=>$audition,'statuses'=>self::STATUSES]); }
    public function update(Request $request,Audition $audition) {
        $audition->update($request->validate(['status'=>['required',Rule::in(self::STATUSES)],'notes'=>['nullable','string','max:10000']]));
        return back()->with('saved','Valutazione salvata.');
    }
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
    public function attach(Request $request,Audition $audition) {
        $request->validate(['video'=>['required','file','mimetypes:video/mp4,video/quicktime,video/webm','extensions:mp4,mov,webm','max:51200']]);
        $path=$request->file('video')->store('auditions','local'); $old=$audition->video_path;
        try { $audition->update(['video_path'=>$path,'video_mime'=>$request->file('video')->getMimeType()]); }
        catch(\Throwable $e) { Storage::disk('local')->delete($path); throw $e; }
        if($old) { Storage::disk('local')->delete($old); }
        return back()->with('saved','Video associato al provino.');
    }
    public function confirmDelete(Audition $audition) { return view('casting.delete',compact('audition')); }
    public function destroy(Request $request,Audition $audition) {
        $request->validate(['confirmation'=>['required',Rule::in([$audition->first_name.' '.$audition->last_name])]],['confirmation.in'=>'Il nome non corrisponde. La candidatura non è stata eliminata.']);
        /** La cancellazione della scheda avviene soltanto dopo quella del materiale video privato. */
        if($audition->video_path && Storage::disk('local')->exists($audition->video_path)) {
            abort_unless(Storage::disk('local')->delete($audition->video_path),500,'Impossibile eliminare il video. La scheda è stata conservata.');
        }
        $audition->delete();
        return redirect()->route('casting.index')->with('saved','Candidatura e video eliminati.');
    }
}


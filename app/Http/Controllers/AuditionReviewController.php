<?php
namespace App\Http\Controllers;
use App\Models\{Audition,AuditionComment,AuditionVideo,AuditionVote};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
class AuditionReviewController extends Controller {
    public const VOTES=['red'=>'Non idoneo','amber'=>'Da rivedere','green'=>'Idoneo'];
    private function message(AuditionComment $comment): array {return ['id'=>$comment->id,'author'=>$comment->user?->name??'Utente non disponibile','own'=>$comment->user_id===auth()->id(),'body'=>$comment->body,'time'=>$comment->created_at->timezone('Europe/Rome')->format('d/m/Y H:i')];}
    public function comments(Request $request,Audition $audition){$data=$request->validate(['after'=>['required','integer','min:0']]);return response()->json(['comments'=>$audition->comments()->with('user')->where('id','>',$data['after'])->orderBy('id')->limit(100)->get()->map(fn($comment)=>$this->message($comment))]);}
    public function comment(Request $request,Audition $audition){
        $data=$request->validate(['body'=>['required','string','max:4000','regex:/\S/u']]);
        /** L'autore proviene dalla sessione, mai dai campi inviati dal browser. */
        $comment=$audition->comments()->create(['user_id'=>$request->user()->id,'body'=>trim($data['body'])]);
        if($request->expectsJson())return response()->json(['comment'=>$this->message($comment->load('user'))],201);
        return redirect(route('casting.show',$audition).'#commenti')->with('saved','Commento aggiunto.');
    }
    public function vote(Request $request,Audition $audition){
        $data=$request->validate(['color'=>['required',Rule::in(array_keys(self::VOTES))]]);
        /** La chiave unica impedisce più voti dello stesso operatore sulla stessa candidatura. */
        AuditionVote::upsert([['audition_id'=>$audition->id,'user_id'=>$request->user()->id,'color'=>$data['color'],'created_at'=>now(),'updated_at'=>now()]],['audition_id','user_id'],['color','updated_at']);
        return redirect(route('casting.show',$audition).'#valutazioni')->with('saved','Il tuo voto è stato salvato.');
    }
    public function video(AuditionVideo $video)
    {
        $audition = $video->audition;
        abort_if($audition === null, 404);
        Gate::authorize('review-audition', $audition);

        return app(AuditionController::class)->video(new Audition(['video_path'=>$video->file_path, 'video_mime'=>$video->file_mime]));
    }
}

<?php
namespace App\Http\Controllers;
use App\Models\{Audition,MaterialRequest};
use Illuminate\Support\Facades\{Auth,Storage};
class MaterialController extends Controller {
    private function authorizeAudition(Audition $audition): void {
        $operator=Auth::check() && Auth::user()->is_casting_operator && (!config('casting.local_access_only') || in_array(request()->ip(),['127.0.0.1','::1'],true));
        
        abort_unless($operator,404);
    }
    public function photo(Audition $audition){$this->authorizeAudition($audition);abort_unless($audition->photo_path && Storage::disk('local')->exists($audition->photo_path),404);return Storage::disk('local')->response($audition->photo_path,null,['Content-Type'=>$audition->photo_mime,'Cache-Control'=>'private, no-store','X-Content-Type-Options'=>'nosniff']);}
    public function file(MaterialRequest $materialRequest){
        $this->authorizeAudition($materialRequest->audition);abort_unless($materialRequest->file_path && Storage::disk('local')->exists($materialRequest->file_path),404);
        return Storage::disk('local')->download($materialRequest->file_path,'materiale.'.match($materialRequest->file_mime){'application/pdf'=>'pdf','video/mp4'=>'mp4','video/quicktime'=>'mov','video/webm'=>'webm','image/png'=>'png','image/webp'=>'webp',default=>'jpg'},['Content-Type'=>$materialRequest->file_mime,'Cache-Control'=>'private, no-store','X-Content-Type-Options'=>'nosniff']);
    }
    public function watch(MaterialRequest $materialRequest){
        $this->authorizeAudition($materialRequest->audition);abort_unless($materialRequest->kind==='video' && $materialRequest->file_path,404);
        return app(AuditionController::class)->video(new Audition(['video_path'=>$materialRequest->file_path,'video_mime'=>$materialRequest->file_mime]));
    }
}

<?php
namespace App\Services;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Cache,RateLimiter};
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Validation\ValidationException;
class AuditionUploadGuard {
    public function run(Request $request,callable $store){
        /** La cache conserva un identificativo HMAC temporaneo e non l'indirizzo IP in chiaro. */
        $fingerprint=hash_hmac('sha256',(string)$request->ip(),(string)config('app.key'));$key='audition-intake:'.$fingerprint;
        try{return Cache::lock($key.':lock',120)->block(2,function()use($key,$store){
            if(RateLimiter::tooManyAttempts($key,(int)config('casting.uploads_per_hour',1)))throw ValidationException::withMessages(['upload_limit'=>'È già stata ricevuta una candidatura da questa connessione nell’ultima ora. Attendi prima di inviarne un’altra.']);
            $result=$store();RateLimiter::hit($key,3600);return $result;
        });}catch(LockTimeoutException){throw ValidationException::withMessages(['upload_limit'=>'È già in corso un invio da questa connessione. Attendi prima di riprovare.']);}
    }
}

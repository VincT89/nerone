<?php
namespace App\Services;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
class LocalCaptcha {
    public function issue(): array {
        $active=array_filter(session('audition_captcha',[]),fn($item)=>($item['expires']??0)>time());
        $active=array_slice($active,-2,null,true);$id=Str::random(32);$code=str_pad((string)random_int(0,999999),6,'0',STR_PAD_LEFT);
        $active[$id]=['cipher'=>Crypt::encryptString($code),'expires'=>time()+1800];session(['audition_captcha'=>$active]);
        return ['id'=>$id,'image'=>route('audition.captcha.image',$id),'audio'=>route('audition.captcha.audio',$id)];
    }
    private function code(string $id): ?string {
        $record=session('audition_captcha.'.$id);
        if(!$record || ($record['expires']??0)<time())return null;
        try{$code=Crypt::decryptString($record['cipher']);return preg_match('/^[0-9]{6}$/D',$code)?$code:null;}catch(\Throwable){return null;}
    }
    public function verify(string $id,string $answer): bool {
        $code=$this->code($id);
        /** Un codice viene consumato anche quando la risposta è errata, impedendo tentativi ripetuti sulla stessa immagine. */
        session()->forget('audition_captcha.'.$id);
        return $code!==null && hash_equals($code,trim($answer));
    }
    public function image(string $id){
        $code=$this->code($id);abort_unless($code,404);abort_unless(function_exists('imagecreatetruecolor'),503,'Verifica di sicurezza temporaneamente non disponibile.');
        $image=imagecreatetruecolor(246,78);imagefill($image,0,0,imagecolorallocate($image,246,246,246));
        for($i=0;$i<8;$i++)imageline($image,random_int(0,245),random_int(0,77),random_int(0,245),random_int(0,77),imagecolorallocate($image,random_int(150,210),random_int(150,210),random_int(150,210)));
        for($i=0;$i<6;$i++)imagettftext($image,random_int(25,29),random_int(-12,12),15+$i*36,random_int(48,61),imagecolorallocate($image,random_int(15,55),random_int(15,55),random_int(15,55)),public_path('assets/Manrope.ttf'),$code[$i]);
        ob_start();imagepng($image);$png=ob_get_clean();imagedestroy($image);
        return response($png)->header('Content-Type','image/png')->header('Cache-Control','private, no-store');
    }
    private function wave(string $digit): array {
        /** Il percorso dipende esclusivamente dalle cifre generate sul server, senza usare nomi di file forniti dal visitatore. */
        $path=storage_path('app/captcha-audio/'.$digit.'.wav');abort_unless(is_file($path),503);$bytes=file_get_contents($path);$fmt=null;$data=null;
        for($offset=12;$offset+8<=strlen($bytes);){$kind=substr($bytes,$offset,4);$length=unpack('V',substr($bytes,$offset+4,4))[1];abort_if($offset+8+$length>strlen($bytes),503);$chunk=substr($bytes,$offset+8,$length);if($kind==='fmt ')$fmt=$chunk;if($kind==='data')$data=$chunk;$offset+=8+$length+($length%2);}
        abort_unless($fmt!==null && $data!==null,503);return [$fmt,$data];
    }
    public function audio(string $id){
        $code=$this->code($id);abort_unless($code,404);$format=null;$samples='';
        foreach(str_split($code) as $digit){[$fmt,$data]=$this->wave($digit);if($format===null)$format=$fmt;abort_unless($format===$fmt,503);$properties=unpack('vencoding/vchannels/Vrate/Vbytes/valign/vbits',$fmt);abort_unless($properties['encoding']===1 && $properties['bits']===16,503);$pause=(int)($properties['rate']*random_int(14,26)/100)*$properties['align'];$samples.=$data.str_repeat("\0",$pause);}
        $body='WAVEfmt '.pack('V',strlen($format)).$format.(strlen($format)%2?"\0":'').'data'.pack('V',strlen($samples)).$samples;
        return response('RIFF'.pack('V',strlen($body)).$body)->header('Content-Type','audio/wav')->header('Cache-Control','private, no-store');
    }
}

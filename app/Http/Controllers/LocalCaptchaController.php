<?php
namespace App\Http\Controllers;
use App\Services\LocalCaptcha;
class LocalCaptchaController extends Controller {
    public function renew(LocalCaptcha $captcha){return response()->json($captcha->issue())->header('Cache-Control','private, no-store');}
    public function image(string $challenge,LocalCaptcha $captcha){return $captcha->image($challenge);}
    public function audio(string $challenge,LocalCaptcha $captcha){return $captcha->audio($challenge);}
}

<?php
namespace App\Http\Controllers;

use App\Services\LocalCaptcha;
use Illuminate\Http\Request;

class LocalCaptchaController extends Controller
{
    public function renew(Request $request, LocalCaptcha $captcha)
    {
        $data = $request->validate(['captcha_id'=>['nullable','string','alpha_num:ascii','size:32']]);
        return response()->json($captcha->issue($data['captcha_id'] ?? null))->header('Cache-Control', 'private, no-store');
    }
}

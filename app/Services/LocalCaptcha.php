<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class LocalCaptcha
{
    private const SESSION_KEY = 'audition_captchas';

    public function issue(?string $previousId = null): array
    {
        $session = session();
        try {
            $challenge = app('captcha')->create('audition', true);
        } finally {
            $session->forget('captcha');
        }

        $now = now()->timestamp;
        $active = array_filter($session->get(self::SESSION_KEY, []), fn (array $item) => $item['expires'] > $now);
        if ($previousId !== null) {
            unset($active[$previousId]);
        }
        $active = array_slice($active, -2, null, true);
        $id = Str::random(32);

        /** La chiave della libreria resta nella sessione della scheda che ha richiesto il codice. */
        $active[$id] = ['key'=>$challenge['key'], 'expires'=>$now + config('captcha.audition.expire')];
        $session->put(self::SESSION_KEY, $active);

        return ['id'=>$id, 'image'=>$challenge['img']];
    }

    public function verify(string $id, string $answer): bool
    {
        $challenge = session()->pull(self::SESSION_KEY.'.'.$id);
        if (!$challenge || $challenge['expires'] <= now()->timestamp) {
            return false;
        }

        return Cache::lock('audition-captcha:'.$id, 10)
            ->get(fn () => app('captcha')->check_api($answer, $challenge['key'], 'audition')) === true;
    }
}

<?php

namespace App\Services;

use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Cache,RateLimiter};
use Illuminate\Validation\ValidationException;

class AuditionUploadGuard
{
    public function connectionKey(Request $request): string
    {
        /** La cache conserva un identificativo HMAC temporaneo e non l'indirizzo IP in chiaro. */
        $address = (string) $request->ip();
        $packed = inet_pton($address);
        if ($packed !== false) {
            $address = inet_ntop($packed);
            if (str_starts_with($address, '::ffff:')) {
                $address = substr($address, 7);
            }
        }

        return 'audition-intake:'.hash_hmac('sha256', $address, (string) config('app.key'));
    }

    public function retryAfter(Request $request): int
    {
        $key = $this->connectionKey($request);
        return RateLimiter::tooManyAttempts($key, (int) config('casting.uploads_per_hour'))
            ? RateLimiter::availableIn($key)
            : 0;
    }

    public function ensureCapacity(Request $request): void
    {
        if ($this->retryAfter($request) > 0) {
            throw ValidationException::withMessages([
                'upload_limit'=>'È già stata ricevuta una candidatura da questa connessione nell’ultima ora. Attendi prima di inviarne un’altra.',
            ]);
        }
    }

    public function run(Request $request, callable $store)
    {
        $key = $this->connectionKey($request);
        try {
            return Cache::lock($key.':lock', 120)->block(2, function () use ($request, $key, $store) {
                /** Il secondo controllo avviene sotto blocco per impedire che due invii simultanei superino la quota. */
                $this->ensureCapacity($request);
                $result = $store();
                RateLimiter::hit($key, 3600);
                return $result;
            });
        } catch (LockTimeoutException) {
            throw ValidationException::withMessages([
                'upload_limit'=>'È già in corso un invio da questa connessione. Attendi prima di riprovare.',
            ]);
        }
    }
}

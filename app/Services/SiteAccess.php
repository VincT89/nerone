<?php

namespace App\Services;

use Illuminate\Http\Request;

class SiteAccess
{
    public function isEnabled(): bool
    {
        /** La variabile assente mantiene il blocco; soltanto un valore esplicitamente vuoto pubblica il sito. */
        return config('casting.site_access_password') !== '';
    }

    public function allows(Request $request): bool
    {
        if (!$this->isEnabled()) {
            return true;
        }

        $grant = $request->session()->get('site_access_granted');
        $password = config('casting.site_access_password');

        return is_string($password) && is_string($grant) && hash_equals($this->fingerprint($password), $grant);
    }

    public function unlock(Request $request, string $password): bool
    {
        if (!$this->isEnabled()) {
            return true;
        }

        $configured = config('casting.site_access_password');
        if (!is_string($configured)) {
            return false;
        }

        $expected = $this->fingerprint($configured);
        if (!hash_equals($expected, $this->fingerprint($password))) {
            return false;
        }

        /** La sessione viene rigenerata per prevenire la fissazione e conserva soltanto una prova derivata della password. */
        $request->session()->regenerate();
        $request->session()->put('site_access_granted', $expected);

        return true;
    }

    private function fingerprint(string $password): string
    {
        return hash_hmac('sha256', $password, (string) config('app.key'));
    }
}

<?php

namespace App\Services;

use Illuminate\Http\Request;

class SiteAccess
{
    public function isEnabled(): bool
    {
        return (string) config('casting.site_access_password', '') !== '';
    }

    public function allows(Request $request): bool
    {
        if (!$this->isEnabled()) {
            return true;
        }

        $grant = $request->session()->get('site_access_granted');

        return is_string($grant) && hash_equals($this->fingerprint((string) config('casting.site_access_password')), $grant);
    }

    public function unlock(Request $request, string $password): bool
    {
        if (!$this->isEnabled()) {
            return true;
        }

        $expected = $this->fingerprint((string) config('casting.site_access_password'));
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

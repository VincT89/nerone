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

    public function checkEntry(Request $request): void
    {
        if (!$this->isEnabled() || !$this->allows($request)) {
            return;
        }

        $redirect = $request->session()->pull('site_access_redirect');
        if (!$request->boolean('_site_entry') && ($redirect === $request->getPathInfo() || $this->isInternalNavigation($request))) {
            return;
        }

        $request->session()->forget('site_access_granted');
    }

    public function continueAfterRedirect(Request $request, string $target): void
    {
        $origin = parse_url($target);
        if (is_array($origin) && (!isset($origin['host']) || $this->sameOrigin($request, $origin))) {
            $request->session()->put('site_access_redirect', $origin['path'] ?? '/');
        }
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
        $request->session()->put('site_access_redirect', '/');

        return true;
    }

    private function isInternalNavigation(Request $request): bool
    {
        $source = parse_url($request->headers->get('Referer', ''));
        $fetchSite = $request->headers->get('Sec-Fetch-Site');
        if (!is_array($source) || ($fetchSite !== null && $fetchSite !== 'same-origin') || !$this->sameOrigin($request, $source)) {
            return false;
        }

        $path = $source['path'] ?? '/';
        /** Il riferimento distingue la navigazione interna; l'autorizzazione richiede comunque la prova verificata nella sessione. */
        return $path !== $request->getPathInfo()
            && (in_array($path, ['/', '/candidati', '/privacy', '/cookie'], true) || preg_match('#^/ricevuto/[a-f0-9-]{36}$#i', $path) === 1);
    }

    private function sameOrigin(Request $request, array $origin): bool
    {
        $scheme = $origin['scheme'] ?? null;
        $port = $origin['port'] ?? ($scheme === 'https' ? 443 : 80);

        return $scheme === $request->getScheme()
            && strtolower($origin['host'] ?? '') === strtolower($request->getHost())
            && $port === $request->getPort();
    }

    private function fingerprint(string $password): string
    {
        return hash_hmac('sha256', $password, (string) config('app.key'));
    }
}

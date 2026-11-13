<?php

namespace App\Providers;

use Mews\Captcha\CaptchaServiceProvider;

class AuditionCaptchaServiceProvider extends CaptchaServiceProvider
{
    /** Le route del pacchetto restano disattivate: il modulo emette i codici con i propri limiti. */
    public function boot(): void
    {
    }
}

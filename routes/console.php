<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/** Il blocco copre anche l'ultimo job avviato prima del limite di durata del worker. */
Schedule::command('queue:work self-tapes --queue=self-tapes --stop-when-empty --timeout=1000 --max-time=300')
    ->everyMinute()
    ->withoutOverlapping(30);

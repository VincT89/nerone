<?php

namespace App\Services;

use App\Jobs\CompressSelfTape;
use Illuminate\Queue\Events\JobAttempted;
use Illuminate\Queue\Jobs\DatabaseJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class SelfTapeQueueRecovery
{
    public function handle(JobAttempted $event): void
    {
        $job = $event->job;
        if (!$event->exception || $event->connectionName !== 'self-tapes' || !$job instanceof DatabaseJob
            || $job->getQueue() !== 'self-tapes' || $job->resolveName() !== CompressSelfTape::class || $job->hasFailed()) {
            return;
        }

        $config = config('queue.connections.self-tapes');
        $reservation = $job->getJobRecord();
        $backoff = explode(',', (string) ($job->backoff() ?? 15));
        $delay = (int) $backoff[min($job->attempts() - 1, count($backoff) - 1)];

        try {
            /** Se Laravel ha già rilasciato il job, la riga originale è sparita. Tocchiamo solo la prenotazione appena terminata. */
            DB::connection($config['connection'])->table($config['table'])
                ->where('id', $job->getJobId())
                ->where('queue', $job->getQueue())
                ->where('attempts', $job->attempts())
                ->where('reserved_at', $reservation->reserved_at)
                ->update(['reserved_at'=>null, 'available_at'=>now()->timestamp + $delay]);
        } catch (Throwable) {
            /** Se il database è ancora indisponibile, il recupero torna alla scadenza nativa di Laravel. */
            Log::warning('La prenotazione del self-tape sarà recuperata alla scadenza.', ['job_id'=>$job->getJobId()]);
        }
    }
}

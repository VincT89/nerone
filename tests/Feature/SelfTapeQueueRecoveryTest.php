<?php

namespace Tests\Feature;

use App\Jobs\CompressSelfTape;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\WorkerOptions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\TestCase;

class SelfTapeQueueRecoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_worker_retries_when_acknowledging_the_job_fails(): void
    {
        $queue = Queue::connection('self-tapes');
        $id = $queue->push(new CompressSelfTape(100));
        $job = $queue->pop('self-tapes');
        $this->assertNotNull($job);

        $failNextDelete = true;
        DB::connection()->beforeExecuting(function (string $query) use (&$failNextDelete) {
            if ($failNextDelete && str_starts_with($query, 'delete from "jobs"')) {
                $failNextDelete = false;
                throw new RuntimeException('Conferma temporaneamente indisponibile.');
            }
        });

        try {
            app('queue.worker')->process('self-tapes', $job, new WorkerOptions);
            $this->fail('La conferma del job doveva fallire.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Conferma temporaneamente indisponibile.', $exception->getMessage());
        }

        $this->assertDatabaseHas('jobs', ['id'=>$id, 'reserved_at'=>null, 'attempts'=>1]);
        $this->assertNull($queue->pop('self-tapes'));
        $this->travel(16)->seconds();
        $retry = $queue->pop('self-tapes');
        $this->assertNotNull($retry);
        $this->assertSame(2, $retry->attempts());

        app('queue.worker')->process('self-tapes', $retry, new WorkerOptions);
        $this->assertDatabaseMissing('jobs', ['id'=>$id]);
    }

    public function test_an_interrupted_worker_leaves_a_job_recoverable_after_the_lease_expires(): void
    {
        $queue = Queue::connection('self-tapes');
        $queue->push(new CompressSelfTape(100));
        $abandoned = $queue->pop('self-tapes');
        $this->assertNotNull($abandoned);
        $lease = config('queue.connections.self-tapes.retry_after');

        $this->travel($lease - 1)->seconds();
        $this->assertNull($queue->pop('self-tapes'));

        $this->travel(2)->seconds();
        $retry = $queue->pop('self-tapes');
        $this->assertNotNull($retry);
        $this->assertSame($abandoned->getJobId(), $retry->getJobId());
        $this->assertSame(2, $retry->attempts());
    }
}

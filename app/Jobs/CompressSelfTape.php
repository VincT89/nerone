<?php

namespace App\Jobs;

use App\Models\Audition;
use App\Models\AuditionVideo;
use App\Services\SelfTapeCompression;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class CompressSelfTape implements ShouldQueue
{
    use Queueable;

    public int $tries = 4;
    public int $timeout = 1000;
    public array $backoff = [15, 45, 120];

    public function __construct(public int $videoId)
    {
    }

    public function handle(SelfTapeCompression $compression): void
    {
        $video = AuditionVideo::find($this->videoId);
        if ($video?->compression_status === AuditionVideo::COMPRESSED) {
            $this->removeSource();
            return;
        }
        if ($video?->compression_status !== AuditionVideo::QUEUED) {
            return;
        }

        $disk = Storage::disk('local');
        $source = $video->file_path;
        $directory = 'self-tape-processing/'.$video->id;
        $prepared = DB::transaction(function () use ($video, $disk, $source, $directory): bool {
            if (!$this->lockAvailableAudition($video->audition_id)) {
                return false;
            }
            if (!preg_match('#^audition-videos/[A-Za-z0-9_.-]+$#D', $source) || !$disk->exists($source)) {
                throw new RuntimeException('Il self-tape originale non è disponibile.');
            }
            if (!$disk->makeDirectory($directory)) {
                throw new RuntimeException('Impossibile preparare lo spazio per la conversione.');
            }

            return true;
        });
        if (!$prepared) {
            return;
        }

        $destination = 'audition-videos/'.Str::uuid().'.mp4';
        $moved = false;

        try {
            $bytes = $compression->encode($disk->path($source), $video->file_mime, $disk->size($source), $disk->path($directory));
            DB::transaction(function () use ($video, $disk, $source, $bytes, $directory, $destination, &$moved) {
                if (!$this->lockAvailableAudition($video->audition_id)) {
                    return;
                }
                $current = AuditionVideo::lockForUpdate()->find($this->videoId);
                if ($current?->compression_status !== AuditionVideo::QUEUED || $current->file_path !== $source) {
                    return;
                }
                if ($bytes === null) {
                    $current->update(['compression_status'=>AuditionVideo::ORIGINAL]);
                    return;
                }

                /** L’originale resta associato alla scheda finché l’MP4 non è salvato e registrato. */
                if (!$disk->move($directory.'/encoded.mp4', $destination)) {
                    throw new RuntimeException('Impossibile salvare il self-tape convertito.');
                }
                $moved = true;
                $current->update([
                    'file_path'=>$destination,
                    'file_mime'=>'video/mp4',
                    'bytes'=>$bytes,
                    'compression_status'=>AuditionVideo::COMPRESSED,
                    'compression_source_path'=>$source,
                ]);
            });
        } catch (Throwable $error) {
            if ($moved) {
                $disk->delete($destination);
            }
            throw $error;
        } finally {
            $disk->deleteDirectory($directory);
        }

        if ($moved) {
            $this->removeSource();
        }
    }

    private function lockAvailableAudition(int $auditionId): bool
    {
        /** La stessa riga coordina la creazione dei file con l’avvio della cancellazione. */
        return Audition::whereKey($auditionId)->whereNull('deletion_requested_at')->lockForUpdate()->first(['id']) !== null;
    }

    private function removeSource(): void
    {
        DB::transaction(function () {
            $video = AuditionVideo::lockForUpdate()->find($this->videoId);
            if (!$video?->compression_source_path) {
                return;
            }
            $disk = Storage::disk('local');
            $path = $video->compression_source_path;
            if ($disk->exists($path) && !$disk->delete($path)) {
                throw new RuntimeException('L’MP4 è disponibile. La pulizia del file temporaneo verrà riprovata.');
            }
            $video->update(['compression_source_path'=>null]);
        });
    }

    public function failed(?Throwable $error): void
    {
        AuditionVideo::whereKey($this->videoId)->where('compression_status', AuditionVideo::QUEUED)
            ->update(['compression_status'=>AuditionVideo::FAILED]);
    }
}

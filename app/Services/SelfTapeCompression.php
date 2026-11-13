<?php

namespace App\Services;

use RuntimeException;
use Symfony\Component\Process\Process;

class SelfTapeCompression
{
    private const TARGET_RATIO = 0.60;
    private const MAX_BYTES = 100_000_000;
    private const AUDIO_BITRATE = 192_000;

    public function __construct(private SelfTape $selfTape)
    {
    }

    public function encode(string $source, string $mime, int $bytes, string $workspace): ?int
    {
        if ($bytes <= 0 || $bytes > self::MAX_BYTES) {
            throw new RuntimeException('Dimensione del self-tape fuori limite.');
        }

        $media = $this->selfTape->inspect($source, $mime);
        $input = ['-protocol_whitelist','file','-threads','2','-noautorotate',
            '-f',$mime === 'video/webm' ? 'matroska' : 'mov','-i',$source];
        $audio = $this->audioOptions($media, $input, $workspace);
        $overhead = max(16_000, (int) ceil($media['duration'] * 3_000));
        $target = (int) floor($bytes * self::TARGET_RATIO);
        $targetBitrate = (int) floor(8 * ($target - $audio['bytes'] - $overhead) / $media['duration']);
        $maxBitrate = (int) floor(8 * (self::MAX_BYTES - 2_000_000 - $audio['bytes'] - $overhead) / $media['duration']);
        $minimumBitrate = max(250_000, min(3_000_000, intdiv($media['width'] * $media['height'], 3)));
        $bitrate = min($maxBitrate, max($targetBitrate, $minimumBitrate));
        if ($bitrate < 100_000) {
            throw new RuntimeException('Non c’è spazio sufficiente per convertire il self-tape con questo audio.');
        }

        /** Evita di ricodificare MP4 già leggeri quando il bitrate minimo annulla il risparmio. */
        if ($mime === 'video/mp4' && $bitrate * $media['duration'] / 8 + $audio['bytes'] + $overhead >= $bytes) {
            return null;
        }

        $video = [...$input,'-map','0:V:0','-fps_mode','passthrough','-c:v','libx264','-preset','veryfast',
            '-pix_fmt','yuv420p','-b:v',(string) $bitrate,'-threads','2','-passlogfile','encode'];
        $this->run([...$video,'-pass','1','-an','-f','null','-'], $workspace, 420);
        $this->run([...$video,'-pass','2',...$audio['options'],'-map_metadata','-1','-map_chapters','-1',
            '-movflags','+faststart','-f','mp4','encoded.mp4'], $workspace, 420);

        $output = $workspace.DIRECTORY_SEPARATOR.'encoded.mp4';
        $outputBytes = filesize($output);
        if ($outputBytes === false || $outputBytes <= 0 || $outputBytes > self::MAX_BYTES) {
            throw new RuntimeException('Il self-tape convertito supera il limite di peso o è vuoto.');
        }
        if ($mime === 'video/mp4' && $outputBytes >= $bytes) {
            return null;
        }

        $converted = $this->selfTape->inspect($output, 'video/mp4');
        if (abs($converted['duration'] - $media['duration']) > 0.25
            || ($media['audio'] !== null && $converted['audio'] !== 'aac')
            || ($media['width'] > 0 && [$converted['width'],$converted['height']] !== [$media['width'],$media['height']])) {
            throw new RuntimeException('Il self-tape convertito non conserva le caratteristiche richieste.');
        }

        return $outputBytes;
    }

    private function audioOptions(array $media, array $input, string $workspace): array
    {
        if ($media['audio'] === null) {
            return ['bytes'=>0, 'options'=>['-an']];
        }
        if ($media['audio'] === 'aac') {
            /** Misura il peso dell’AAC per riservargli spazio senza ricodificarlo. */
            $this->run([...$input,'-map','0:a:0','-vn','-c:a','copy','-f','mp4','audio.m4a'], $workspace, 30);
            $bytes = filesize($workspace.DIRECTORY_SEPARATOR.'audio.m4a');
            if ($bytes === false || $bytes <= 0) {
                throw new RuntimeException('Non è stato possibile misurare la traccia audio.');
            }
            return ['bytes'=>$bytes, 'options'=>['-map','0:a:0','-c:a','copy']];
        }

        return [
            'bytes'=>(int) ceil($media['duration'] * self::AUDIO_BITRATE / 8),
            'options'=>['-map','0:a:0','-c:a','aac','-b:a',(string) self::AUDIO_BITRATE],
        ];
    }

    private function run(array $arguments, string $workspace, int $timeout): void
    {
        $process = new Process([
            config('casting.ffmpeg_binary'),'-hide_banner','-loglevel','error','-nostdin','-y','-filter_threads','1',
            ...$arguments,
        ], $workspace, timeout: $timeout);
        $process->run();
        if (!$process->isSuccessful()) {
            throw new RuntimeException('FFmpeg non ha completato la conversione del self-tape.');
        }
    }
}

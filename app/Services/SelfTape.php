<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Process\Exception\RuntimeException;
use Symfony\Component\Process\Process;

class SelfTape
{
    public function duration(UploadedFile $file): float
    {
        return $this->inspect($file->getPathname(), (string) $file->getMimeType())['duration'];
    }

    public function inspect(string $path, string $mime): array
    {
        $demuxer = $mime === 'video/webm' ? 'matroska' : 'mov';
        /** Il selettore V esclude copertine e miniature. Un pacchetto video basta per escludere le tracce vuote. */
        $probe = new Process([
            config('casting.ffmpeg_binary'), '-hide_banner', '-nostdin', '-protocol_whitelist', 'file',
            '-f', $demuxer, '-i', $path, '-map', '0:V:0', '-c:v', 'copy',
            '-frames:v', '1', '-abort_on', 'empty_output', '-f', 'null', '-',
        ], timeout: 8);

        try {
            $probe->run();
        } catch (RuntimeException) {
            throw ValidationException::withMessages(['self_tape'=>'La verifica del video non è disponibile. Riprova più tardi.']);
        }

        $matches = preg_match_all('/^  Duration: (\d{2,}):(\d{2}):(\d{2}\.\d+), start:/m', $probe->getErrorOutput(), $times, PREG_SET_ORDER);
        if (!$probe->isSuccessful() || $matches !== 1) {
            throw ValidationException::withMessages(['self_tape'=>'Il file deve contenere un video leggibile. Esporta il self-tape in MP4 e riprova.']);
        }

        $time = $times[0];
        $seconds = (int) $time[1] * 3600 + (int) $time[2] * 60 + (float) $time[3];
        if ($seconds <= 0 || $seconds > 300) {
            throw ValidationException::withMessages(['self_tape'=>'Il self-tape deve durare al massimo 5 minuti.']);
        }

        $report = $probe->getErrorOutput();
        $input = strstr($report, 'Stream mapping:', true) ?: $report;
        $output = strstr($report, 'Output #0') ?: '';
        preg_match('/^\s+Stream #0:\d+[^:\r\n]*: Audio: ([a-z0-9_]+)/m', $input, $audio);
        preg_match('/Video: [^\r\n]+?,\s*(\d{2,6})x(\d{2,6})(?=[,\s\[])/', $output, $dimensions);

        return [
            'duration'=>$seconds,
            'audio'=>$audio[1] ?? null,
            'width'=>(int) ($dimensions[1] ?? 0),
            'height'=>(int) ($dimensions[2] ?? 0),
        ];
    }
}

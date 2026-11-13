<?php

namespace Database\Seeders;

use App\Http\Requests\StoreAuditionRequest;
use App\Models\Audition;
use App\Models\AuditionVideo;
use App\Models\Selection;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use RuntimeException;

class ProvaNeroSeeder extends Seeder
{
    private const EMAIL = 'collaudo.nero@example.test';

    private const ATTACHMENTS = [
        'photo' => 'prova-nero/foto-primo-piano.jpg',
        'full_photo' => 'prova-nero/foto-figura-intera.jpg',
        'cv' => 'prova-nero/cv-prova-nero.pdf',
        'self_tape' => 'prova-nero/self-tape-prova-nero.mp4',
    ];

    public function run(): void
    {
        $created = DB::transaction(function (): bool {
            $selection = Selection::latest('id')->lockForUpdate()->first();
            if ($selection === null) {
                throw new RuntimeException('Configura una selezione prima di creare la candidatura di prova.');
            }

            $existing = Audition::where('email', self::EMAIL)->first();
            if ($existing !== null) {
                if (!$existing->is_demo || $existing->deletion_requested_at !== null) {
                    throw new RuntimeException('L’indirizzo di prova è già in uso. Verifica la scheda nel gestionale.');
                }

                return false;
            }

            $files = $this->attachments();
            $audition = $selection->auditions()->create([
                'reference' => (string) Str::uuid(),
                'phase_id' => $selection->phases()->first()?->id,
                'first_name' => 'Prova',
                'last_name' => 'Nero',
                'email' => self::EMAIL,
                'phone' => '0000000000',
                'city' => 'Roma',
                'discipline' => 'ensemble',
                'presentation' => 'Candidatura fittizia per il collaudo del gestionale NERO.',
                'is_demo' => true,
                'photo_path' => self::ATTACHMENTS['photo'],
                'photo_mime' => $files['photo']->getMimeType(),
                'full_photo_path' => self::ATTACHMENTS['full_photo'],
                'full_photo_mime' => $files['full_photo']->getMimeType(),
                'cv_path' => self::ATTACHMENTS['cv'],
                'application_details' => [
                    'birth_date' => '1990-06-21',
                    'nationality' => 'Italiana',
                    'other_roles' => false,
                    'voice_range' => 'Do3–Do5',
                    'ensemble_song' => 'Brano di prova NERO',
                    'training' => 'Dati di collaudo',
                    'languages' => 'Italiano, inglese B2',
                    'declaration' => false,
                    'signed_place' => 'Roma',
                    'signed_date' => today('Europe/Rome')->toDateString(),
                    'signature' => 'Prova Nero (fittizia)',
                ],
            ]);

            /** Il video di prova è già in MP4 e si può riprodurre appena creata la scheda. */
            $audition->videos()->create([
                'position' => 1,
                'file_path' => self::ATTACHMENTS['self_tape'],
                'file_mime' => $files['self_tape']->getMimeType(),
                'bytes' => $files['self_tape']->getSize(),
                'original_bytes' => $files['self_tape']->getSize(),
                'compression_status' => AuditionVideo::ORIGINAL,
            ]);

            return true;
        }, 3);

        $this->command?->info($created ? 'Candidatura di prova creata.' : 'La candidatura Prova Nero è già presente.');
    }

    private function attachments(): array
    {
        $disk = Storage::disk('local');
        $files = [];
        foreach (self::ATTACHMENTS as $field => $path) {
            if (!$disk->exists($path)) {
                throw new RuntimeException('Carica prima l’allegato mancante in storage/app/private/'.$path);
            }

            $files[$field] = new UploadedFile($disk->path($path), basename($path), test: true);
        }

        $request = new StoreAuditionRequest;
        $validator = Validator::make($files, Arr::only($request->rules(), array_keys($files)), $request->messages());
        if ($validator->fails()) {
            throw new RuntimeException($validator->errors()->first());
        }

        return $files;
    }
}

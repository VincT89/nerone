<?php

namespace App\Http\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAuditionRequest extends FormRequest
{
    public function rules(): array
    {
        $today = today('Europe/Rome');
        $photo = ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'extensions:jpg,jpeg,png,webp', 'max:5120', Rule::dimensions()->maxWidth(5000)->maxHeight(5000)];

        return [
            'selection_id'=>['required','integer'],
            'first_name'=>['required','string','max:80'],
            'last_name'=>['required','string','max:80'],
            'birth_date'=>['required','date_format:Y-m-d','before_or_equal:'.$today->copy()->subYearsNoOverflow(18)->toDateString()],
            'nationality'=>['nullable','string','max:100'],
            'city'=>['required','string','max:100'],
            'email'=>['required','email','max:254'],
            'phone'=>[
                'bail', 'required', 'string', 'max:40', 'regex:/\A\+?[0-9() .-]+\z/',
                static function (string $attribute, string $value, Closure $fail): void {
                    $digits = str_replace(['+', '(', ')', ' ', '.', '-'], '', $value);
                    /** Il prefisso di accesso internazionale 00 non conta tra le cifre del numero. */
                    if (!str_starts_with($value, '+') && str_starts_with($digits, '00')) {
                        $digits = substr($digits, 2);
                    }
                    if (strlen($digits) < 7 || strlen($digits) > 15) {
                        $fail('Inserisci un numero di telefono da 7 a 15 cifre.');
                    }
                },
            ],
            'discipline'=>['required',Rule::in(array_keys(config('audition_roles')))],
            'voice_range'=>['exclude_unless:discipline,ensemble','nullable','string','max:300'],
            'ensemble_song'=>['exclude_unless:discipline,ensemble','nullable','string','max:300'],
            'ensemble_skills'=>['exclude_unless:discipline,ensemble','nullable','string','max:2000'],
            'choreography_song'=>['nullable','string','max:300'],
            'other_roles'=>['required','boolean'],
            'profession'=>['nullable','string','max:300'],
            'training'=>['nullable','string','max:3000'],
            'experience'=>['nullable','string','max:3000'],
            'skills'=>['nullable','string','max:3000'],
            'languages'=>['nullable','string','max:2000'],
            'self_tape'=>['bail','required','file','max:97656.25','mimetypes:video/mp4,video/quicktime,video/webm','extensions:mp4,mov,webm'],
            'cv'=>['required','file','mimes:pdf','extensions:pdf','max:10240'],
            'photo'=>$photo,
            'full_photo'=>$photo,
            'showreel'=>['nullable','url:http,https','max:2000'],
            'social'=>['nullable','string','max:500'],
            'presentation'=>['nullable','string','max:2000'],
            'adult'=>['accepted'],
            'consent'=>['accepted'],
            'declaration'=>['accepted'],
            'declaration_version'=>['required','string',Rule::in([config('casting.declaration.version')])],
            'signed_place'=>['required','string','max:100'],
            'signed_date'=>['required','date_format:Y-m-d','before_or_equal:'.$today->toDateString()],
            'signature'=>['required','string','max:160'],
            'website'=>['nullable','max:0'],
            'captcha_id'=>['required','string','alpha_num:ascii','size:32'],
            'captcha_code'=>['required','string','regex:/^[0-9]{6}$/D'],
        ];
    }

    public function messages(): array
    {
        return [
            'required'=>'Completa il campo «:attribute».',
            'email.email'=>'Inserisci un indirizzo email valido.',
            'phone.regex'=>'Inserisci un numero di telefono valido.',
            'birth_date.before_or_equal'=>'Per candidarti devi avere almeno 18 anni.',
            'signed_date.before_or_equal'=>'La data della dichiarazione non può essere futura.',
            'showreel.url'=>'Inserisci un link completo con https:// o http://.',
            'accepted'=>'Conferma questa voce per proseguire.',
            'declaration_version.required'=>'Rileggi e conferma la dichiarazione per proseguire.',
            'declaration_version.in'=>'La dichiarazione è stata aggiornata. Rileggila e confermala prima di inviare.',
            'self_tape.required'=>'Carica il tuo self-tape.',
            'self_tape.max'=>'Il self-tape deve pesare al massimo 100 MB.',
            'self_tape.mimetypes'=>'Scegli un video MP4, MOV o WebM.',
            'self_tape.extensions'=>'Scegli un video MP4, MOV o WebM.',
            'cv.required'=>'Allega il CV.',
            'cv.mimes'=>'Allega il CV in formato PDF.',
            'cv.extensions'=>'Allega il CV in formato PDF.',
            'cv.max'=>'Il CV deve pesare al massimo 10 MB.',
            'photo.required'=>'Allega una foto in primo piano.',
            'full_photo.required'=>'Allega una foto a figura intera.',
            'photo.max'=>'La foto in primo piano deve pesare al massimo 5 MB.',
            'full_photo.max'=>'La foto a figura intera deve pesare al massimo 5 MB.',
            'photo.image'=>'Per il primo piano, scegli una foto JPG, PNG o WebP.',
            'full_photo.image'=>'Per la figura intera, scegli una foto JPG, PNG o WebP.',
        ];
    }

    public function attributes(): array
    {
        return [
            'first_name'=>'nome', 'last_name'=>'cognome', 'birth_date'=>'data di nascita',
            'city'=>'città / paese di residenza', 'email'=>'indirizzo email', 'phone'=>'telefono cellulare',
            'discipline'=>'ruolo', 'other_roles'=>'disponibilità per altri ruoli',
            'signed_place'=>'luogo', 'signed_date'=>'data della dichiarazione', 'signature'=>'firma',
            'captcha_code'=>'codice di sicurezza',
        ];
    }
}

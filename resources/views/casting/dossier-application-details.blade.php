@php
    $details = $audition->application_details;
    $detailGroups = [
        'Dati e preferenze'=>[
            'Data di nascita'=>\Illuminate\Support\Carbon::parse($details['birth_date'])->format('d/m/Y'),
            'Nazionalità'=>$details['nationality'] ?? null,
            'Disponibile per altri ruoli'=>$details['other_roles'] ? 'Sì' : 'No',
            'Vocalità ed estensione'=>$details['voice_range'] ?? null,
            'Brano presentato'=>$details['ensemble_song'] ?? null,
            'Abilità vocali o di movimento'=>$details['ensemble_skills'] ?? null,
            'Brano della coreografia'=>$details['choreography_song'] ?? null,
        ],
        'Profilo professionale'=>[
            'Professione / attività artistica'=>$details['profession'] ?? null,
            'Formazione'=>$details['training'] ?? null,
            'Esperienze professionali'=>$details['experience'] ?? null,
            'Competenze specifiche'=>$details['skills'] ?? null,
            'Lingue e livelli'=>$details['languages'] ?? null,
            'Contatti social'=>$details['social'] ?? null,
            'Informazioni aggiuntive'=>$audition->presentation,
        ],
        'Dichiarazione e invio'=>[
            'Riferimento'=>$audition->reference,
            'Invio registrato il'=>$audition->created_at->timezone('Europe/Rome')->format('d/m/Y · H:i:s'),
            'Correttezza dei dati e autorizzazione sui materiali'=>$details['declaration'] ? 'Confermate' : null,
            'Testo accettato'=>$details['declaration_text'] ?? null,
            'Versione della dichiarazione'=>$details['declaration_version'] ?? null,
            'Accettata il'=>isset($details['declaration_accepted_at'])
                ? \Illuminate\Support\Carbon::parse($details['declaration_accepted_at'])->timezone('Europe/Rome')->format('d/m/Y · H:i:s')
                : null,
            'Informativa privacy'=>$audition->consented_at?->timezone('Europe/Rome')->format('d/m/Y · H:i'),
            'Luogo e data'=>$details['signed_place'].' · '.\Illuminate\Support\Carbon::parse($details['signed_date'])->format('d/m/Y'),
            'Firma'=>$details['signature'],
        ],
    ];
@endphp
@if($audition->cv_path || $audition->full_photo_path)
    <div class="dossier-attachments" aria-label="Allegati della candidatura">
        @if($audition->cv_path)<a href="{{ route('casting.cv',$audition) }}">Scarica CV (PDF)</a>@endif
        @if($audition->full_photo_path)<a href="{{ route('casting.full-photo',$audition) }}" target="_blank" rel="noopener">Foto a figura intera</a>@endif
    </div>
@endif
@foreach($detailGroups as $group=>$fields)
    <details class="dossier-summary">
        <summary>{{ $group }}</summary>
        <dl class="dossier-details">
            @foreach($fields as $label=>$value)
                @if(filled($value))<div><dt>{{ $label }}</dt><dd>{{ $value }}</dd></div>@endif
            @endforeach
            @if($group==='Profilo professionale' && !empty($details['showreel']))
                <div><dt>Showreel</dt><dd><a href="{{ $details['showreel'] }}" target="_blank" rel="noopener noreferrer">Apri il link del candidato</a></dd></div>
            @endif
        </dl>
        @if($group==='Profilo professionale' && !array_filter($fields) && empty($details['showreel']))
            <p>Nessun dato aggiuntivo.</p>
        @endif
    </details>
@endforeach

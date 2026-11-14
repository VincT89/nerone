@extends('layout')
@section('title','Scheda di candidatura')
@section('body-class','application-page')
@section('public-nav-start')
    <a class="nav-back-project" href="{{ route('home') }}#il-provino">Torna al progetto</a>
@endsection
@section('content')
<div class="application-layout">
    <header class="application-intro">
        <figure class="application-photo"><img src="{{ asset('assets/nero/teatro.jpg') }}" width="2000" height="1333" alt="Palcoscenico e platea di un grande teatro" fetchpriority="high"></figure>
        <div class="application-intro-copy">
            <h1>Scheda di candidatura</h1>
            <p>Audizione online per il cast di {{ $project['title'] }}</p>
            @if($isUpcoming)
                <p class="intro-note"><strong>Candidature dal <x-casting-opening-time :opens-at="$opensAt" /></strong></p>
            @elseif(!$isOpen)
                <p class="intro-note"><strong>Candidature chiuse</strong></p>
            @elseif($deadline)
                <p class="intro-note"><strong>Scadenza candidature</strong><br><x-casting-opening-time :opens-at="$deadline" :show-time="false" /></p>
            @endif
            <nav class="application-index" aria-label="Sezioni della candidatura">
                <a href="#dati-personali">Dati personali</a>
                <a href="#ruolo">Ruolo e brani</a>
                <a href="#video">Self-tape</a>
                <a href="#profilo">Profilo professionale</a>
                <a href="#allegati">Allegati</a>
                <a href="#dichiarazione">Dichiarazione e invio</a>
            </nav>
        </div>
    </header>
    <div class="application-content">
        <section class="application-overview" aria-labelledby="selection-path">
            <x-casting-calendar :project="$project" :opens-at="$opensAt" :deadline="$deadline" :is-open="$isOpen" :is-upcoming="$isUpcoming" :audition-month="$auditionMonth" />
            <div class="application-overview-copy">
                <h2 id="selection-path">Il percorso di selezione</h2>
                <p>Le candidature si presentano esclusivamente online tramite questo modulo. Prepara una presentazione in inglese, una prova di canto, un monologo e, solo per performer, una prova coreografica.</p>
                <p>La produzione valuterà il self-tape insieme al curriculum e alle due fotografie recenti. Solo i profili selezionati saranno convocati a {{ $project['audition_city'] }} a {{ $auditionMonth }}. Date e luogo verranno comunicati direttamente via email.</p>
                <h3>Profili ricercati</h3>
                <p>{{ $project['profiles'] }}</p>
            </div>
        </section>
        <form class="audition-form" action="{{ route('audition.store') }}" method="post" enctype="multipart/form-data" data-audition-form>
        @csrf
        @if($errors->any())
            <div class="form-errors" role="alert">
                <p><strong>Controlla i campi indicati.</strong></p>
                <ul>@foreach($errors->getMessages() as $field=>$messages)
                    @foreach($messages as $message)
                        <li>@if($field==='selection_id' || $field==='upload_limit' || $field==='captcha_id'){{ $message }}@else<a href="#{{ $field==='declaration_version' ? 'declaration' : $field }}">{{ $message }}</a>@endif</li>
                    @endforeach
                @endforeach</ul>
                <p>Seleziona nuovamente il self-tape, il CV e le due foto prima di riprovare.</p>
            </div>
        @endif
        @unless($isOpen)
            <p class="form-errors" role="status">{{ $isUpcoming ? 'Puoi consultare la scheda e preparare i materiali prima dell’apertura.' : 'Al momento le candidature sono chiuse.' }}</p>
        @endunless
        @if($isOpen && $uploadRetryAfter > 0)
            <p class="form-errors" role="status">È già stata ricevuta una candidatura da questa connessione. Potrai inviarne un’altra tra circa {{ max(1, (int) ceil($uploadRetryAfter / 60)) }} min. Ricarica il modulo dopo l’attesa.</p>
        @endif
        <p class="application-required">I campi contrassegnati con un asterisco (*) sono obbligatori.</p>
        <input type="hidden" name="selection_id" value="{{ $selection?->id }}">

        <fieldset class="application-section" id="dati-personali">
            <legend>Dati personali</legend>
            <div class="field-grid">
                <label>Nome *<input id="first_name" name="first_name" value="{{ old('first_name') }}" autocomplete="given-name" maxlength="80" required></label>
                <label>Cognome *<input id="last_name" name="last_name" value="{{ old('last_name') }}" autocomplete="family-name" maxlength="80" required></label>
                <label>Data di nascita *<input id="birth_date" name="birth_date" type="date" value="{{ old('birth_date') }}" max="{{ $latestBirthDate }}" autocomplete="bday" required></label>
                <label>Nazionalità<input id="nationality" name="nationality" value="{{ old('nationality') }}" maxlength="100"></label>
                <label class="application-wide">Città / Paese di residenza *<input id="city" name="city" value="{{ old('city') }}" maxlength="100" required></label>
                <label>Indirizzo email *<input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" maxlength="254" required></label>
                <label>Telefono cellulare *<input id="phone" name="phone" type="tel" value="{{ old('phone') }}" autocomplete="tel" maxlength="40" required></label>
            </div>
        </fieldset>

        <fieldset class="application-section" id="ruolo">
            <legend>Candidatura</legend>
            <label>Ruolo per il quale ti candidi *
                <select id="discipline" name="discipline" required data-audition-role>
                    <option value="">Scegli il ruolo</option>
                    <optgroup label="Personaggi principali o coveraggi">
                        @foreach($roles as $key=>$role)
                            @if($key!=='ensemble')<option value="{{ $key }}" @selected(old('discipline')===$key)>{{ $role['name'] }}</option>@endif
                        @endforeach
                    </optgroup>
                    <option value="ensemble" @selected(old('discipline')==='ensemble')>{{ $roles['ensemble']['name'] }}</option>
                </select>
            </label>
            <details class="role-requirements" data-role-catalog @if(old('discipline') && old('discipline')!=='ensemble') open @endif>
                <summary>Vocalità e brani per personaggi principali o coveraggi</summary>
                <p>Per le cover, mantieni ove possibile le tonalità originali.</p>
                @foreach($roles as $key=>$role)
                    @if(isset($role['songs']))
                        <details data-role-guide="{{ $key }}" @if(old('discipline')===$key) open @endif>
                            <summary>{{ $role['name'] }}</summary>
                            <div class="role-instructions">
                                <p><strong>Vocalità / estensione richiesta</strong><br>{{ $role['range'] }}</p>
                                <p><strong>Brani da preparare</strong></p>
                                <ul>@foreach($role['songs'] as $song)<li>{{ $song }}</li>@endforeach</ul>
                            </div>
                        </details>
                    @endif
                @endforeach
            </details>
            <div class="ensemble-fields" data-ensemble-fields>
                <h2>Ensemble / personaggi minori</h2>
                <p>Compila questi campi se ti candidi per l’ensemble o per i personaggi minori.</p>
                <label class="full-field">Vocalità ed estensione<input id="voice_range" name="voice_range" value="{{ old('voice_range') }}" maxlength="300"></label>
                <label class="full-field">Titolo del brano presentato <span>(preferibilmente pop / rock in inglese)</span><input id="ensemble_song" name="ensemble_song" value="{{ old('ensemble_song') }}" maxlength="300"></label>
                <label class="full-field">Eventuali abilità vocali o di movimento <span>(acrobatica, break dance, ecc.)</span><textarea id="ensemble_skills" name="ensemble_skills" rows="3" maxlength="2000">{{ old('ensemble_skills') }}</textarea></label>
            </div>
            <fieldset class="application-options" id="other_roles">
                <legend>Desideri essere preso/a in considerazione anche per altri ruoli? *</legend>
                <label><input type="radio" name="other_roles" value="1" required @checked((string)old('other_roles')==='1')> Sì</label>
                <label><input type="radio" name="other_roles" value="0" required @checked((string)old('other_roles')==='0')> No</label>
            </fieldset>
        </fieldset>

        <fieldset class="application-section audition-videos" id="video">
            <legend>Materiali richiesti per il self-tape</legend>
            <dl class="self-tape-guidance">
                <div><dt>Prova di canto</dt><dd>Massimo 2 minuti, preferibilmente nelle tonalità originali.</dd></div>
                <div><dt>Prova recitata</dt><dd>Monologo brillante e/o monologo drammatico in lingua inglese, della durata massima di 1 minuto.</dd></div>
                <div><dt>Prova di danza / movimento</dt><dd>Prova coreografica della durata massima di 1 minuto, richiesta solo ai performer.</dd></div>
            </dl>
            <label class="full-field">Canzone utilizzata per la coreografia <span>(se presentata)</span><input id="choreography_song" name="choreography_song" value="{{ old('choreography_song') }}" maxlength="300"></label>
            <div class="self-tape-technical" id="video-requirements">
                <h2>Informazioni tecniche</h2>
                <p>Prepara un unico video di massimo <strong>5 minuti</strong>. Inizia con una presentazione in inglese indicando nome, cognome, età, città di domicilio e ruolo. Inserisci poi la canzone e il monologo. Se ti candidi come performer, includi anche la prova coreografica.</p>
                <p>Gira in orizzontale, a mezzo busto e con una buona illuminazione. La parte cantata va presentata senza interventi di post-produzione. Puoi unire in sequenza riprese separate della presentazione e delle prove.</p>
                <p>Ti consigliamo di comprimere il self-tape prima dell’invio e di esportarlo in MP4 con video H.264 e audio AAC. Rivedi il file prima di caricarlo per verificare che l’immagine resti nitida e l’audio sia chiaro.</p>
                <p>Carica il video qui sotto. Formati accettati MP4, MOV o WebM, massimo <strong>100 MB</strong>.</p>
            </div>
            <div class="audition-video-field">
                <label for="self_tape">Self-tape *<input id="self_tape" type="file" name="self_tape" accept="video/mp4,video/quicktime,video/webm,.mp4,.mov,.webm" required aria-describedby="video-requirements" data-audition-video></label>
                <p class="video-selection" data-video-selection aria-live="polite"></p>
                <video controls playsinline preload="metadata" hidden data-video-local-preview aria-label="Anteprima del self-tape"></video>
            </div>
        </fieldset>

        <fieldset class="application-section" id="profilo">
            <legend>Profilo professionale</legend>
            <label>Professione attuale / principale attività artistica<input id="profession" name="profession" value="{{ old('profession') }}" maxlength="300"></label>
            <label class="full-field">Formazione<textarea id="training" name="training" rows="3" maxlength="3000">{{ old('training') }}</textarea></label>
            <label class="full-field">Esperienze professionali precedenti<textarea id="experience" name="experience" rows="3" maxlength="3000">{{ old('experience') }}</textarea></label>
            <label class="full-field">Competenze specifiche<textarea id="skills" name="skills" rows="3" maxlength="3000">{{ old('skills') }}</textarea></label>
            <label class="full-field">Lingue parlate <span>(indica per ciascuna B1, B2, C1, C2 o madrelingua)</span><textarea id="languages" name="languages" rows="3" maxlength="2000">{{ old('languages') }}</textarea></label>
        </fieldset>

        <fieldset class="application-section" id="allegati">
            <legend>Materiali per la candidatura</legend>
            <p id="cv-guidance">CV in PDF, massimo 10 MB.</p>
            <label class="full-field">Curriculum vitae *<input id="cv" type="file" name="cv" accept="application/pdf,.pdf" aria-describedby="cv-guidance" data-max-file-bytes="10485760" required></label>
            <p id="photo-guidance">Allega due foto recenti in JPG, PNG o WebP. Massimo 5 MB per foto.</p>
            <div class="field-grid application-photos">
                <label>Foto in primo piano *<input id="photo" type="file" name="photo" accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp" aria-describedby="photo-guidance" data-max-file-bytes="5242880" required></label>
                <label>Foto a figura intera *<input id="full_photo" type="file" name="full_photo" accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp" aria-describedby="photo-guidance" data-max-file-bytes="5242880" required></label>
            </div>
            <label class="full-field">Link a showreel o video personale <span>(Vimeo, YouTube o sito personale)</span><input id="showreel" type="url" name="showreel" value="{{ old('showreel') }}" maxlength="2000" autocomplete="url"></label>
            <label class="full-field">Contatto Instagram / TikTok / LinkedIn<input id="social" name="social" value="{{ old('social') }}" maxlength="500"></label>
        </fieldset>

        <fieldset class="application-section">
            <legend>Informazioni aggiuntive</legend>
            <label>Ci sono altre informazioni che desideri comunicarci riguardo alla tua candidatura?<textarea id="presentation" name="presentation" rows="4" maxlength="2000">{{ old('presentation') }}</textarea></label>
        </fieldset>

        <fieldset class="application-section">
            <legend>Informativa sulla privacy</legend>
            <p>I dati personali e i materiali forniti tramite questo modulo saranno trattati per gestire l’audizione, valutare i candidati e svolgere la selezione. L’accesso è riservato alle persone coinvolte nell’organizzazione e nella selezione. I dati sono conservati per il periodo necessario a queste finalità, nel rispetto del Regolamento (UE) 2016/679.</p>
            <p>Consulta l’<a href="{{ route('privacy') }}" target="_blank" rel="noopener">informativa completa sulla privacy</a> per i dati del titolare, le finalità e la base giuridica, la conservazione, i destinatari, gli eventuali trasferimenti e l’esercizio dei tuoi diritti.</p>
            <label class="check-field"><input id="consent" type="checkbox" name="consent" value="1" required @checked(old('consent'))><span>Confermo di aver letto e compreso l’informativa sulla privacy e acconsento al trattamento dei dati e dei materiali per la gestione dell’audizione. *</span></label>
        </fieldset>

        <fieldset class="application-section" id="dichiarazione">
            <legend>Dichiarazione e invio</legend>
            <input type="hidden" name="declaration_version" value="{{ config('casting.declaration.version') }}">
            <label class="check-field"><input id="declaration" type="checkbox" name="declaration" value="1" required @checked(old('declaration') && !$errors->has('declaration_version'))><span>{{ config('casting.declaration.text') }} *</span></label>
            <label class="check-field"><input id="adult" type="checkbox" name="adult" value="1" required @checked(old('adult'))><span>Confermo di avere almeno 18 anni. *</span></label>
            <div class="field-grid">
                <label>Luogo *<input id="signed_place" name="signed_place" value="{{ old('signed_place') }}" maxlength="100" required></label>
                <label>Data *<input id="signed_date" type="date" name="signed_date" value="{{ old('signed_date',$today) }}" max="{{ $today }}" required></label>
                <label class="application-wide">Firma <span>(nome e cognome)</span> *<input id="signature" name="signature" value="{{ old('signature') }}" maxlength="160" required></label>
            </div>
        </fieldset>

        <div class="honeypot" aria-hidden="true"><label>Lascia vuoto<input name="website" tabindex="-1" autocomplete="off"></label></div>
        @if($isOpen)
        <fieldset class="local-captcha application-section" id="verifica" data-local-captcha data-renew="{{ route('audition.captcha.renew') }}">
            <legend>Verifica di sicurezza</legend>
            <input type="hidden" name="captcha_id" value="{{ $captcha['id'] }}" data-captcha-id>
            <div class="captcha-picture"><img src="{{ $captcha['image'] }}" alt="Immagine con il codice di verifica di sei cifre" width="246" height="60" data-captcha-image><button type="button" data-captcha-renew>Cambia codice</button></div>
            <label for="captcha_code">Trascrivi le sei cifre *<input id="captcha_code" name="captcha_code" type="text" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="off" spellcheck="false" required data-captcha-code></label>
            <p data-captcha-status aria-live="polite"></p>
            <noscript><p>Per ottenere un codice diverso, ricarica la pagina prima di compilare il modulo.</p></noscript>
        </fieldset>
        @endif
        <div class="submit-row"><p data-audition-status aria-live="polite">{{ $isOpen ? 'Attendi la conferma prima di chiudere questa pagina.' : 'L’invio sarà disponibile nel periodo di apertura.' }}</p><button class="button primary" type="submit" @disabled(!$isOpen || $uploadRetryAfter > 0)>Invia la candidatura</button></div>
        </form>
    </div>
</div>
@endsection

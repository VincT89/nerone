<?php
namespace App\Http\Controllers;
use App\Models\Audition;
use App\Models\AuditionVideo;
use App\Models\Selection;
use App\Jobs\CompressSelfTape;
use App\Http\Requests\StoreAuditionRequest;
use App\Services\{AuditionUploadGuard,CastingCalendar,LocalCaptcha,SelfTape};
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use League\Flysystem\FilesystemException;
class AuditionController extends Controller {
    public const ROLES = ['Canto'=>'Cantante','Recitazione'=>'Attore / Attrice','Danza'=>'Ballerino / Ballerina','Musica'=>'Musicista'];
    public const STATUSES = ['Da vedere','In valutazione','Da richiamare','Convocato dal vivo','Nel cast','Archiviato'];
    public static function roleLabels(): array
    {
        return array_map(fn (array $role) => $role['name'], config('audition_roles')) + self::ROLES;
    }

    public function home(CastingCalendar $calendar)
    {
        $selection = Selection::with('phases')->latest('id')->first();

        return view('home', [
            ...$calendar->status($selection),
            'project'=>config('casting.project'),
        ]);
    }

    public function apply(Request $request, AuditionUploadGuard $uploads, CastingCalendar $calendar)
    {
        $selection = Selection::with('phases')->latest('id')->first();
        $status = $calendar->status($selection);

        return response()->view('apply', [
            ...$status,
            'roles'=>config('audition_roles'),
            'selection'=>$selection,
            'project'=>config('casting.project'),
            'latestBirthDate'=>today('Europe/Rome')->subYearsNoOverflow(18)->toDateString(),
            'today'=>today('Europe/Rome')->toDateString(),
            'captcha'=>$status['isOpen'] ? app(LocalCaptcha::class)->issue() : null,
            'uploadRetryAfter'=>$status['isOpen'] ? $uploads->retryAfter($request) : 0,
        ])->header('Cache-Control','no-store');
    }

    public function store(StoreAuditionRequest $request)
    {
        $data = $request->validated();
        if (!app(LocalCaptcha::class)->verify($data['captcha_id'], $data['captcha_code'])) {
            throw ValidationException::withMessages(['captcha_code'=>'Il codice di verifica non è corretto o è scaduto. Inserisci il nuovo codice.']);
        }
        $data['email'] = mb_strtolower(trim($data['email']));

        $audition = app(AuditionUploadGuard::class)->run($request, function () use ($data) {
            $selection = Selection::latest('id')->first();
            if (!$selection?->acceptsApplications() || $selection->id !== (int) $data['selection_id']) {
                throw ValidationException::withMessages(['selection_id'=>'Il periodo per le candidature non è aperto.']);
            }

            $duration = app(SelfTape::class)->duration($data['self_tape']);
            $paths = [];
            $keepAttachments = false;
            try {
                /** I file sono copiati prima della transazione, così gli invii non attendono la copia degli altri candidati. */
                $photoPath = $this->storeAttachment($data['photo'], 'candidate-photos', $paths);
                $fullPhotoPath = $this->storeAttachment($data['full_photo'], 'candidate-photos', $paths);
                $cvPath = $this->storeAttachment($data['cv'], 'candidate-cvs', $paths);
                $tape = $data['self_tape'];
                $videoMime = $tape->getMimeType();
                $videoBytes = $tape->getSize();
                $videoName = $tape->hashName();
                $disk = Storage::disk('local');
                if (!$disk->makeDirectory('audition-videos')) {
                    throw new \RuntimeException('Impossibile preparare lo spazio per il self-tape.');
                }
                /** PHP ha già scritto il caricamento su disco. Lo spostiamo nello storage privato senza ricopiarlo sullo stesso volume. */
                $tape->move($disk->path('audition-videos'), $videoName);
                $videoPath = 'audition-videos/'.$videoName;
                $paths[] = $videoPath;

                $audition = DB::transaction(function () use ($data, $duration, $photoPath, $fullPhotoPath, $cvPath, $videoPath, $videoMime, $videoBytes) {
                    /** Il blocco condiviso coordina la chiusura del casting senza serializzare gli invii. */
                    $selection = Selection::latest('id')->sharedLock()->first();
                    if (!$selection?->acceptsApplications() || $selection->id !== (int) $data['selection_id']) {
                        throw ValidationException::withMessages(['selection_id'=>'Il periodo per le candidature non è aperto.']);
                    }
                    $existing = $this->existingAudition($selection->id, $data['email']);
                    if ($existing) {
                        return $existing;
                    }

                    $details = collect($data)->only([
                        'birth_date','nationality','voice_range','ensemble_song','ensemble_skills',
                        'choreography_song','other_roles','profession','training','experience','skills',
                        'languages','showreel','social','signed_place','signed_date','signature',
                    ])->all();
                    $submittedAt = now();
                    $details['other_roles'] = (bool) $data['other_roles'];
                    $details['declaration'] = true;
                    $details['declaration_version'] = $data['declaration_version'];
                    $details['declaration_text'] = config('casting.declaration.text');
                    $details['declaration_accepted_at'] = $submittedAt->toIso8601String();
                    $details['self_tape_seconds'] = $duration;

                    $audition = new Audition([
                        ...collect($data)->only(['selection_id','first_name','last_name','email','phone','city','discipline','presentation'])->all(),
                        'phase_id'=>$selection->phases()->first()?->id,
                        'reference'=>(string) Str::uuid(),
                        'application_details'=>$details,
                        'photo_path'=>$photoPath,
                        'photo_mime'=>$data['photo']->getMimeType(),
                        'full_photo_path'=>$fullPhotoPath,
                        'full_photo_mime'=>$data['full_photo']->getMimeType(),
                        'cv_path'=>$cvPath,
                        'consented_at'=>$submittedAt,
                        'adult_confirmed_at'=>$submittedAt,
                        'privacy_version'=>'2026-11-12',
                    ]);
                    $audition->setCreatedAt($submittedAt)->save();
                    $video = $audition->videos()->create([
                        'position'=>1,
                        'file_path'=>$videoPath,
                        'file_mime'=>$videoMime,
                        'bytes'=>$videoBytes,
                        'original_bytes'=>$videoBytes,
                        'compression_status'=>AuditionVideo::QUEUED,
                    ]);
                    /** La coda usa lo stesso database: candidatura e lavoro vengono confermati insieme. */
                    Queue::connection('self-tapes')->push(new CompressSelfTape($video->id));
                    return $audition;
                }, 3);
                $keepAttachments = $audition->wasRecentlyCreated;
                return $audition;
            } catch (UniqueConstraintViolationException $error) {
                /** Due richieste concorrenti con la stessa email devono restituire la ricevuta già registrata. */
                $existing = $this->existingAudition((int) $data['selection_id'], $data['email']);
                if ($existing) {
                    return $existing;
                }
                throw $error;
            } finally {
                if (!$keepAttachments) {
                    Storage::disk('local')->delete($paths);
                }
            }
        });
        return redirect()->route('audition.received', $audition->reference);
    }

    private function existingAudition(int $selectionId, string $email): ?Audition
    {
        $existing = Audition::where('selection_id', $selectionId)->where('email', $email)->first();
        if ($existing?->deletion_requested_at !== null) {
            throw ValidationException::withMessages(['email'=>'È già presente una candidatura per questo indirizzo. Riprova più tardi.']);
        }

        return $existing;
    }

    private function storeAttachment(UploadedFile $file, string $directory, array &$paths): string
    {
        $path = $file->store($directory, 'local');
        if (!$path) {
            throw new \RuntimeException('Impossibile salvare un allegato della candidatura.');
        }
        $paths[] = $path;
        return $path;
    }
    public function received(string $reference, CastingCalendar $calendar) {
        /** La conferma utilizza un riferimento casuale e non espone i dati del candidato. */
        $audition = Audition::where('reference', $reference)->whereNull('deletion_requested_at')->firstOrFail(['id', 'created_at']);
        $submittedAt = $audition->created_at->timezone('Europe/Rome');
        $videoQueued = $audition->videos()->where('compression_status', AuditionVideo::QUEUED)->exists();
        return response()->view('received', [
            'reference'=>$reference,
            'submittedAt'=>$submittedAt,
            'videoQueued'=>$videoQueued,
            'auditionCity'=>config('casting.project.audition_city'),
            'auditionMonth'=>$calendar->auditionMonth(),
        ])->header('Cache-Control','no-store');
    }
    public function index(Request $request) {
        $roleLabels = self::roleLabels();
        $roles = array_diff_key($roleLabels, self::ROLES);
        $data=$request->validate(['q'=>['nullable','string','max:100'],'discipline'=>['nullable',Rule::in(array_keys($roleLabels))],'status'=>['nullable',Rule::in(self::STATUSES)],'received_from'=>['nullable','date_format:Y-m-d'],'received_to'=>['nullable','date_format:Y-m-d']]);
        if(!empty($data['received_from']) && !empty($data['received_to']) && $data['received_to']<$data['received_from'])throw \Illuminate\Validation\ValidationException::withMessages(['received_to'=>'La data finale deve seguire quella iniziale.']);
        $selection=\App\Models\Selection::latest('id')->first();
        $base=Audition::query()->where('selection_id',$selection?->id)->whereNull('deletion_requested_at');
        /** Il giorno italiano viene convertito in un intervallo UTC, rispettando anche il cambio dell'ora. */
        $today=\Carbon\CarbonImmutable::now('Europe/Rome')->startOfDay();
        $todayCount=(clone $base)->where('created_at','>=',$today->utc())->where('created_at','<',$today->addDay()->utc())->count();
        $roleCounts=(clone $base)->selectRaw('discipline, count(*) as total')->groupBy('discipline')->pluck('total','discipline');
        $query=$base->with('votes')->withCount(['videos','comments']);
        if(!empty($data['received_from']))$query->where('created_at','>=',\Carbon\Carbon::parse($data['received_from'],'Europe/Rome')->startOfDay()->utc());
        if(!empty($data['received_to']))$query->where('created_at','<',\Carbon\Carbon::parse($data['received_to'],'Europe/Rome')->startOfDay()->addDay()->utc());
        if($search=$data['q']??null)$query->where(function($q)use($search){$q->whereLike('first_name',"%{$search}%")->orWhereLike('last_name',"%{$search}%")->orWhereLike('city',"%{$search}%");});
        foreach(['discipline','status'] as $field)if(!empty($data[$field]))$query->where($field,$data[$field]);
        $pendingDeletions = Gate::allows('manage-casting')
            ? Audition::whereNotNull('deletion_requested_at')
                ->orderBy('deletion_requested_at')->orderBy('id')
                ->simplePaginate(10, ['id', 'first_name', 'last_name'], 'eliminazioni')->withQueryString()
            : null;

        return view('casting.index', [
            'auditions'=>$query->latest('id')->paginate(30)->withQueryString(),
            'statuses'=>self::STATUSES,
            'roles'=>$roles,
            'roleLabels'=>$roleLabels,
            'roleCounts'=>$roleCounts,
            'selection'=>$selection,
            'todayCount'=>$todayCount,
            'todayDate'=>$today->toDateString(),
            'pendingDeletions'=>$pendingDeletions,
        ]);
    }
    public function show(Audition $audition){return view('casting.show',['audition'=>$audition->load(['videos','votes.user']),'comments'=>$audition->comments()->with('user')->latest('id')->paginate(50),'operators'=>\App\Models\User::where('is_casting_operator',true)->orderBy('name')->get(),'voteLabels'=>AuditionReviewController::VOTES,'statuses'=>self::STATUSES]);}
    public function update(Request $request,Audition $audition){$audition->update($request->validate(['status'=>['required',Rule::in(self::STATUSES)]]));return back()->with('saved','Stato aggiornato.');}
    public function video(Audition $audition) {
        abort_unless($audition->video_path && Storage::disk('local')->exists($audition->video_path),404);
        /** Il video resta fuori dalla cartella pubblica e anche lo streaming richiede una sessione autorizzata. */
        $disk=Storage::disk('local'); $size=$disk->size($audition->video_path); $start=0; $end=$size-1; $status=200;
        if($range=request()->header('Range')) {
            if(!preg_match('/^bytes=(\d*)-(\d*)$/D',$range,$matches) || ($matches[1]==='' && $matches[2]==='')) { return response('',416,['Content-Range'=>"bytes */{$size}"]); }
            if($matches[1]==='') { $start=max(0,$size-(int)$matches[2]); }
            else { $start=(int)$matches[1]; if($matches[2]!=='') { $end=min($end,(int)$matches[2]); } }
            if($start>$end || $start>=$size) { return response('',416,['Content-Range'=>"bytes */{$size}"]); }
            $status=206;
        }
        $headers=['Content-Type'=>$audition->video_mime,'Cache-Control'=>'private, no-store','X-Content-Type-Options'=>'nosniff','Accept-Ranges'=>'bytes','Content-Length'=>(string)($end-$start+1)];
        if($status===206) { $headers['Content-Range']="bytes {$start}-{$end}/{$size}"; }
        return response()->stream(function() use($disk,$audition,$start,$end) {
            $stream=$disk->readStream($audition->video_path);
            if(!is_resource($stream)) { return; }
            try {
                fseek($stream,$start); $remaining=$end-$start+1;
                while($remaining>0 && !feof($stream) && !connection_aborted()) {
                    $chunk=fread($stream,min(65536,$remaining)); if($chunk===false || $chunk==='') { break; }
                    echo $chunk; $remaining-=strlen($chunk);
                }
            } finally { fclose($stream); }
        },$status,$headers);
    }
    public function confirmDelete(Audition $audition) { return view('casting.delete',compact('audition')); }
    public function destroy(Request $request, Audition $audition)
    {
        $request->validate(
            ['confirmation'=>['required', Rule::in([$audition->first_name.' '.$audition->last_name])]],
            ['confirmation.in'=>'Il nome non corrisponde. Nessun allegato è stato rimosso in questo tentativo.'],
        );

        /** Lo stato deve sopravvivere a un errore di rimozione, perché i file già eliminati non si possono ripristinare con il database. */
        Audition::whereKey($audition->id)->whereNull('deletion_requested_at')->update(['deletion_requested_at'=>now()]);

        $deleted = DB::transaction(function () use ($audition): bool {
            $current = Audition::lockForUpdate()->find($audition->id);
            if (!$current) {
                return true;
            }

            $videos = $current->videos()->lockForUpdate()->get(['id', 'file_path', 'compression_source_path']);
            $paths = array_filter([
                ...$videos->pluck('file_path')->all(),
                ...$videos->pluck('compression_source_path')->all(),
                ...$current->materialRequests()->whereNotNull('file_path')->pluck('file_path')->all(),
                $current->video_path, $current->photo_path, $current->full_photo_path, $current->cv_path,
            ]);
            $disk = Storage::disk('local');
            try {
                foreach (array_unique($paths) as $privatePath) {
                    if (!$disk->delete($privatePath)) {
                        return false;
                    }
                }
                foreach ($videos as $video) {
                    if (!$disk->deleteDirectory('self-tape-processing/'.$video->id)) {
                        return false;
                    }
                }
            } catch (FilesystemException) {
                return false;
            }

            return (bool) $current->delete();
        });

        if (!$deleted) {
            return redirect()->route('casting.confirm-delete', $audition)
                ->withErrors(['deletion'=>'La rimozione di alcuni file è in sospeso. Riprova per completare l’eliminazione.']);
        }

        return redirect()->route('casting.index')->with('saved','Candidatura e allegati eliminati.');
    }
}


<?php
namespace Tests\Feature;
use App\Models\{Audition,AuditionComment,AuditionVote,Candidate,CandidateMessage,Selection,User};
use App\Services\SelfTapeCompression;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{Auth,Crypt,DB,Hash,Storage};
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;
class CandidateWorkflowTest extends TestCase {
    use RefreshDatabase;
    private array $temporaryUploads=[];
    protected function setUp(): void
    {
        $this->mockConsoleOutput = false;
        parent::setUp();
        Storage::fake('local');
        config([
            'casting.uploads_per_hour'=>100,
            'casting.upload_attempts_per_quarter_hour'=>100,
            'casting.project.opening_date'=>'2026-11-01',
        ]);
    }
    /** Le migrazioni di prova non devono mai raggiungere il database delle candidature locali. */
    protected function beforeRefreshingDatabase(){if(config('database.default')!=='sqlite' || DB::connection()->getDatabaseName()!==':memory:')throw new \RuntimeException('Le prove richiedono SQLite in memoria.');}
    protected function tearDown(): void {foreach($this->temporaryUploads as $path)if(is_file($path))unlink($path);parent::tearDown();}
    private function upload(string $content,string $name): UploadedFile {$path=tempnam(sys_get_temp_dir(),'nerone-test-');file_put_contents($path,$content);$this->temporaryUploads[]=$path;return new UploadedFile($path,$name,null,UPLOAD_ERR_OK,true);}
    private function captcha(): array
    {
        $this->withSession([]);
        config(['captcha.characters'=>['7']]);
        $challenge = app(\App\Services\LocalCaptcha::class)->issue();
        return ['captcha_id'=>$challenge['id'], 'captcha_code'=>'777777'];
    }
    private function payload(string $email='persona@example.test'): array
    {
        $picture = file_get_contents(public_path('assets/nero/hero.jpg'));
        return [
            ...$this->captcha(),
            'selection_id'=>Selection::firstOrFail()->id,
            'first_name'=>'Persona','last_name'=>'Artistica','birth_date'=>'1990-06-21',
            'email'=>$email,'phone'=>'0000000000','city'=>'Roma, Italia','nationality'=>'Italiana',
            'discipline'=>'ensemble','voice_range'=>'Do3–Do5','ensemble_song'=>'Brano per il provino',
            'other_roles'=>'0','training'=>'Formazione teatrale','languages'=>'Inglese C1',
            'showreel'=>'https://example.test/showreel','social'=>'@persona',
            'adult'=>'1','consent'=>'1','declaration'=>'1','declaration_version'=>config('casting.declaration.version'),
            'signed_place'=>'Roma','signed_date'=>today('Europe/Rome')->toDateString(),'signature'=>'Persona Artistica',
            'self_tape'=>$this->upload(file_get_contents(public_path('assets/nero/trailer-nero.mp4')),'provino.mp4'),
            'cv'=>$this->upload("%PDF-1.4\n%%EOF\n",'curriculum.pdf'),
            'photo'=>$this->upload($picture,'primo-piano.jpg'),
            'full_photo'=>$this->upload($picture,'figura-intera.jpg'),
        ];
    }
    private function operator(bool $admin=false): User {$user=new User;$user->forceFill(['name'=>'Valutatore '.Str::random(5),'email'=>Str::lower(Str::random(10)).'@example.test','password'=>Str::random(28).'a1','is_casting_operator'=>true,'is_casting_admin'=>$admin])->save();return $user;}
    private function audition(string $email='persona@example.test'): Audition {$this->post('/candidati',$this->payload($email))->assertRedirect();return Audition::where('email',$email)->firstOrFail();}
    public function test_today_badge_uses_the_italian_day_and_current_selection(): void {
        \Carbon\CarbonImmutable::setTestNow(\Carbon\CarbonImmutable::parse('2026-11-09 12:00:00','Europe/Rome'));
        try {
            $previous=Selection::firstOrFail();$current=Selection::create(['title'=>'Selezione corrente','is_open'=>true]);
            foreach ([[$current->id,'2026-11-08 22:59:59','Canto'],[$current->id,'2026-11-08 23:00:00','Canto'],[$current->id,'2026-11-09 22:59:59','Danza'],[$current->id,'2026-11-09 23:00:00','Canto'],[$previous->id,'2026-11-09 10:00:00','Canto']] as $index=>[$selection,$created,$discipline]) {
                DB::table('auditions')->insert(['reference'=>(string)Str::uuid(),'selection_id'=>$selection,'first_name'=>'Persona','last_name'=>'Artistica','email'=>'giorno-'.$index.'@example.test','phone'=>'0000000000','city'=>'Roma','discipline'=>$discipline,'created_at'=>$created,'updated_at'=>$created]);
            }
            $this->actingAs($this->operator(),'web');
            $this->get('/casting')->assertOk()->assertViewHas('todayCount',2)->assertViewHas('todayDate','2026-11-09');
            $this->get('/casting?discipline=Canto')->assertOk()->assertViewHas('todayCount',2);
            $this->get('/casting?received_from=2026-11-09&received_to=2026-11-09')->assertOk()->assertViewHas('todayCount',2)->assertViewHas('auditions',fn($auditions)=>$auditions->total()===2);
            \Carbon\CarbonImmutable::setTestNow(\Carbon\CarbonImmutable::parse('2026-11-11 12:00:00','Europe/Rome'));
            $this->get('/casting')->assertOk()->assertViewHas('todayCount',0);
        } finally {\Carbon\CarbonImmutable::setTestNow();}
    }
    public function test_public_opening_uses_italian_time_and_respects_the_production_period(): void
    {
        config(['casting.project.opening_date'=>'2026-11-17']);
        $this->travelTo(\Carbon\CarbonImmutable::parse('2026-11-16T22:59:59Z'));

        try {
            $this->get('/')->assertOk()->assertSee('Apertura il')->assertSee('17 novembre 2026');
            $this->get('/candidati')->assertOk()->assertDontSee('data-local-captcha', false)
                ->assertSee('Puoi consultare la scheda');
            $this->post('/candidati', $this->payload())->assertSessionHasErrors('selection_id');
            $this->assertDatabaseCount('auditions', 0);

            $this->travelTo(\Carbon\CarbonImmutable::parse('2026-11-16T23:00:00Z'));
            $this->get('/')->assertOk()->assertSee('Candidati online');
            $this->get('/candidati')->assertOk()->assertSee('data-local-captcha', false);

            $selection = Selection::firstOrFail();
            $selection->phases()->firstOrFail()->update(['starts_at'=>'2026-11-18 08:00:00']);
            $this->get('/candidati')->assertOk()->assertSee('18 novembre 2026 alle 09:00')
                ->assertDontSee('data-local-captcha', false);

            $selection->update(['is_open'=>false]);
            $this->get('/')->assertOk()->assertSee('Candidature chiuse')->assertDontSee('Candidati online');
        } finally {
            $this->travelBack();
        }
    }

    public function test_intake_requires_a_self_tape_cv_photos_and_declaration(): void {
        $this->get('/candidati')->assertOk()->assertSee('name="self_tape"',false)->assertSee('Gethsemane')->assertDontSee('videos[0]',false)->assertDontSee('area personale');
        $missing=$this->payload();unset($missing['self_tape'],$missing['cv'],$missing['photo'],$missing['full_photo'],$missing['declaration'],$missing['signature']);
        $this->post('/candidati',$missing)->assertSessionHasErrors(['self_tape','cv','photo','full_photo','declaration','signature']);
        $bad=$this->payload();$bad['self_tape']=$this->upload(file_get_contents(public_path('assets/nero/hero.jpg')),'falso.mp4');$this->post('/candidati',$bad)->assertSessionHasErrors('self_tape');
        $audition=$this->audition();$this->assertSame(1,$audition->videos()->count());
        $this->assertSame('video/mp4',$audition->videos->first()->file_mime);
        $this->assertSame('Do3–Do5',$audition->application_details['voice_range']);
        $this->assertFalse($audition->application_details['other_roles']);
        $this->assertSame('Persona Artistica',$audition->application_details['signature']);
        Storage::disk('local')->assertExists([$audition->videos->first()->file_path,$audition->photo_path,$audition->full_photo_path,$audition->cv_path]);
        $this->assertSame(0,CandidateMessage::count());$this->assertSame(0,Candidate::count());$this->assertNull(config('auth.guards.candidate'));
    }
    public function test_phone_requires_seven_to_fifteen_digits_and_accepts_international_format(): void
    {
        foreach (['+++++', '---()', '+39 1', '00 12345', '+1234567890123456', '123+4567890'] as $phone) {
            $this->postJson('/candidati', ['phone'=>$phone])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('phone');
        }

        foreach (['(202) 555.0100', '+1 (202) 555-0100', '001 (202) 555-0100'] as $phone) {
            $this->postJson('/candidati', ['phone'=>$phone])
                ->assertUnprocessable()
                ->assertJsonMissingValidationErrors('phone');
        }
    }

    public function test_repeat_intake_keeps_the_original_files_and_contact(): void {
        $audition=$this->audition();$paths=$audition->videos->pluck('file_path')->all();$this->post('/candidati',[...$this->payload(),'first_name'=>'Altro'])->assertRedirect();
        $this->assertSame(1,Audition::count());$this->assertSame(1,$audition->fresh()->videos()->count());$this->assertSame($paths,$audition->fresh()->videos->pluck('file_path')->all());$this->assertSame('Persona',$audition->fresh()->first_name);
        $this->assertSame($audition->cv_path,$audition->fresh()->cv_path);
        $this->assertSame($audition->application_details['declaration_accepted_at'],$audition->fresh()->application_details['declaration_accepted_at']);
    }
    public function test_submission_records_the_declaration_version_and_shows_a_public_receipt(): void
    {
        $this->post('/candidati', [...$this->payload(), 'declaration_version'=>'obsolete'])->assertSessionHasErrors('declaration_version');
        $audition = $this->audition();
        $details = $audition->application_details;
        $this->assertSame(config('casting.declaration.text'), $details['declaration_text']);
        $this->assertSame(config('casting.declaration.version'), $details['declaration_version']);
        $this->assertSame($audition->created_at->toIso8601String(), $details['declaration_accepted_at']);

        $this->get(route('audition.received', $audition->reference))->assertOk()
            ->assertSee($audition->reference)->assertSee('Ricevuta il')->assertDontSee('Persona Artistica');
        $this->actingAs($this->operator())->get(route('casting.show', $audition))->assertOk()
            ->assertSee('Dichiarazione e invio')->assertSee($audition->reference)
            ->assertSee($details['declaration_text'])->assertSee('Accettata il');
    }
    public function test_photo_and_video_are_private_and_support_partial_streaming(): void {
        $this->post('/candidati',[...$this->payload(),'photo'=>$this->upload(file_get_contents(public_path('assets/nero/hero.jpg')),'foto.jpg')])->assertRedirect();$audition=Audition::firstOrFail();$video=$audition->videos->first();
        $this->get(route('material.photo',$audition))->assertNotFound();$this->get(route('casting.videos.watch',$video))->assertRedirect(route('login'));
        $this->get(route('casting.full-photo',$audition))->assertRedirect(route('login'));
        $this->get(route('casting.cv',$audition))->assertRedirect(route('login'));
        $this->withSession(['login_candidate_'.sha1(\Illuminate\Auth\SessionGuard::class)=>123])->get(route('material.photo',$audition))->assertNotFound();
        $this->actingAs($this->operator(),'web');$this->get(route('material.photo',$audition))->assertOk()->assertHeader('Cache-Control','no-store, private');
        $this->get(route('casting.videos.watch',$video),['Range'=>'bytes=0-15'])->assertStatus(206)->assertHeader('Content-Length','16');
        $this->get(route('casting.full-photo',$audition))->assertOk()->assertHeader('Content-Type','image/jpeg')->assertHeader('Cache-Control','no-store, private');
        $this->get(route('casting.cv',$audition))->assertOk()->assertHeader('Content-Type','application/pdf')->assertDownload('curriculum.pdf');
        $this->get(route('casting.show',$audition))->assertOk()->assertSee('Ensemble / personaggi minori')->assertSee('Inglese C1')->assertSee('Scarica CV')->assertSee('Self-tape');
        $bad=$this->payload('altra@example.test');$bad['photo']=$this->upload('<svg xmlns="http://www.w3.org/2000/svg"/>','foto.svg');$this->post('/candidati',$bad)->assertSessionHasErrors('photo');
    }
    public function test_login_is_only_for_production_and_candidate_endpoints_are_removed(): void {
        $contact=Candidate::create(['email'=>'contatto@example.test','password'=>Str::random(25)]);$secret=Str::random(28).'a2';$contact->password=$secret;$contact->save();
        $this->post('/login',['email'=>$contact->email,'password'=>$secret])->assertSessionHasErrors('email');$this->assertGuest('web');
        $operator=$this->operator();$operator->password=$secret;$operator->save();$this->post('/login',['email'=>$operator->email,'password'=>$secret])->assertRedirect(route('casting.index'));
        foreach(['/area','/area/accesso','/area/attiva','/casting/posta','/casting/selezioni'] as $url)$this->get($url)->assertNotFound();
        $this->post('/area/recupera',['email'=>$contact->email])->assertNotFound();
        $this->get('/login')->assertRedirect(route('casting.index'));
    }
    public function test_each_operator_can_only_change_their_own_traffic_light(): void {
        $audition=$this->audition();$first=$this->operator();$second=$this->operator();
        $this->actingAs($first,'web')->patch(route('casting.votes.store',$audition),['color'=>'green','user_id'=>$second->id])->assertRedirect();
        $this->assertSame($first->id,AuditionVote::firstOrFail()->user_id);
        $this->actingAs($second,'web')->patch(route('casting.votes.store',$audition),['color'=>'red'])->assertRedirect();
        $this->actingAs($first,'web')->patch(route('casting.votes.store',$audition),['color'=>'amber'])->assertRedirect();
        $this->assertSame(2,AuditionVote::count());$this->assertSame('red',AuditionVote::where('user_id',$second->id)->value('color'));$this->assertSame('amber',AuditionVote::where('user_id',$first->id)->value('color'));
        $this->patch(route('casting.votes.store',$audition),['color'=>'blue'])->assertSessionHasErrors('color');
        $this->get(route('casting.show',$audition))->assertOk()->assertSee($first->name)->assertSee($second->name)->assertDontSee('Abilita accesso')->assertDontSee('Fase');
    }
    public function test_comments_are_attributed_escaped_and_scoped_to_the_audition(): void {
        $audition=$this->audition();$other=$this->audition('altra@example.test');$first=$this->operator();$second=$this->operator();$body='<script>window.test=1</script> Valutazione artistica.';
        $this->actingAs($first,'web')->postJson(route('casting.comments.store',$audition),['body'=>$body,'user_id'=>$second->id])->assertCreated()->assertJsonPath('comment.author',$first->name);
        $this->assertSame($first->id,AuditionComment::firstOrFail()->user_id);$this->get(route('casting.show',$audition))->assertOk()->assertSee('&lt;script&gt;',false)->assertDontSee('<script>window.test',false);
        $this->getJson(route('casting.comments',$other).'?after=0')->assertOk()->assertJsonCount(0,'comments');$this->actingAs($second,'web')->getJson(route('casting.comments',$audition).'?after=0')->assertOk()->assertJsonPath('comments.0.own',false);
        $this->postJson(route('casting.comments.store',$audition),['body'=>'   '])->assertUnprocessable();Auth::guard('web')->logout();$this->getJson(route('casting.comments',$audition).'?after=0')->assertRedirect(route('login'));
    }
    public function test_production_filters_and_single_opening_period(): void {
        $audition=$this->audition();$this->actingAs($this->operator(),'web');$this->patch(route('casting.update',$audition),['status'=>'Convocato dal vivo'])->assertRedirect();
        $this->get('/casting?status=Convocato%20dal%20vivo&received_from='.now()->toDateString().'&received_to='.now()->toDateString())->assertOk()->assertSee('Persona Artistica')->assertSee('applications-table',false);
        $this->get('/casting?status=Archiviato')->assertOk()->assertDontSee('Persona Artistica')->assertSee('Ensemble / personaggi minori')->assertDontSee('Attore / Attrice')->assertDontSee('Ballerino / Ballerina')->assertDontSee('Cantante')->assertDontSee('Musicista');
        $this->get('/casting?discipline=ensemble')->assertOk()->assertSee('Persona Artistica');
        $this->actingAs($this->operator(true), 'web');
        $this->patch(route('casting.settings.update'),['title'=>'Audizioni','is_open'=>1,'starts_at'=>'2026-11-10T09:00','ends_at'=>'2026-11-20T18:00'])->assertRedirect();$this->assertSame('08:00',Selection::firstOrFail()->phases()->first()->starts_at->format('H:i'));
        $this->patch(route('casting.settings.update'),['title'=>'Audizioni','is_open'=>1,'starts_at'=>'2026-11-20T18:00','ends_at'=>'2026-11-10T09:00'])->assertSessionHasErrors('ends_at');
        $this->patch(route('casting.settings.update'),['title'=>'Audizioni','is_open'=>0,'starts_at'=>null,'ends_at'=>null])->assertRedirect();$this->post('/candidati',$this->payload('nuova@example.test'))->assertSessionHasErrors('selection_id');
        $this->get(route('casting.settings'))->assertOk()->assertDontSee('Aggiungi una fase');
    }
    public function test_only_an_admin_can_create_production_accounts(): void {
        $admin=$this->operator(true);$secret=Str::random(24).'a2';$this->actingAs($admin,'web')->post(route('casting.team.store'),['name'=>'Nuovo valutatore','email'=>'valutatore@example.test','password'=>$secret,'password_confirmation'=>$secret])->assertRedirect();
        $created=User::where('email','valutatore@example.test')->firstOrFail();$this->assertTrue($created->is_casting_operator);$this->assertFalse($created->is_casting_admin);$this->assertTrue(Hash::check($secret,$created->password));
        $this->actingAs($created,'web')->get(route('casting.team'))->assertForbidden();$this->post(route('casting.team.store'),['name'=>'Altro'])->assertForbidden();
    }
    public function test_only_an_admin_can_manage_opening_and_delete_an_audition(): void
    {
        $audition = $this->audition();
        $this->actingAs($this->operator(), 'web');

        $this->get(route('casting.show', $audition))->assertOk()
            ->assertDontSee('Apertura audizioni')
            ->assertDontSee('Elimina candidatura');
        $this->get(route('casting.settings'))->assertForbidden();
        $this->patch(route('casting.settings.update'), ['title'=>'Audizioni', 'is_open'=>0])->assertForbidden();
        $this->get(route('casting.confirm-delete', $audition))->assertForbidden();
        $this->delete(route('casting.destroy', $audition), ['confirmation'=>'Persona Artistica'])->assertForbidden();
        $this->assertNull($audition->fresh()->deletion_requested_at);
        $this->assertTrue(Selection::firstOrFail()->is_open);
        Storage::disk('local')->assertExists($audition->photo_path);

        $this->actingAs($this->operator(true), 'web');
        $this->get(route('casting.show', $audition))->assertOk()
            ->assertSee('Apertura audizioni')
            ->assertSee('Elimina candidatura');
        $this->get(route('casting.confirm-delete', $audition))->assertOk();
        $this->delete(route('casting.destroy', $audition), ['confirmation'=>'Nome diverso'])
            ->assertSessionHasErrors('confirmation');
        $this->assertNull($audition->fresh()->deletion_requested_at);
    }

    public function test_deleting_an_audition_removes_all_attachments_and_reviews(): void
    {
        $audition = $this->audition();
        $paths = [$audition->videos->first()->file_path, $audition->photo_path, $audition->full_photo_path, $audition->cv_path];
        $operator = $this->operator();
        $audition->comments()->create(['user_id'=>$operator->id, 'body'=>'Valutazione']);
        $audition->votes()->create(['user_id'=>$operator->id, 'color'=>'green']);

        $this->actingAs($this->operator(true), 'web')
            ->delete(route('casting.destroy', $audition), ['confirmation'=>'Persona Artistica'])
            ->assertRedirect(route('casting.index'));

        $this->assertDatabaseCount('auditions', 0);
        $this->assertSame(0, AuditionVote::count());
        $this->assertSame(0, AuditionComment::count());
        Storage::disk('local')->assertMissing($paths);
    }

    public function test_an_interrupted_deletion_can_be_resumed(): void
    {
        $audition = $this->audition();
        $video = $audition->videos()->firstOrFail();
        $admin = $this->operator(true);
        $disk = Storage::disk('local');
        $directory = 'self-tape-processing/'.$video->id;
        $disk->put($directory.'/encoded.mp4', 'Conversione di prova');

        $failingDisk = Mockery::mock($disk);
        $failingDisk->shouldReceive('delete')->with($audition->photo_path)->once()->andReturn(false);
        Storage::set('local', $failingDisk);
        try {
            $this->actingAs($admin, 'web')
                ->delete(route('casting.destroy', $audition), ['confirmation'=>'Persona Artistica'])
                ->assertRedirect(route('casting.confirm-delete', $audition))
                ->assertSessionHasErrors('deletion');
        } finally {
            Storage::set('local', $disk);
        }

        $this->assertNotNull($audition->fresh()->deletion_requested_at);
        $disk->assertMissing($video->file_path);
        $disk->assertExists([$audition->photo_path, $audition->full_photo_path, $audition->cv_path, $directory.'/encoded.mp4']);
        $this->get(route('casting.index'))->assertOk()
            ->assertSee('Eliminazioni in sospeso')
            ->assertSee(route('casting.confirm-delete', $audition));

        $this->actingAs($this->operator(), 'web');
        $this->get(route('casting.index'))->assertOk()->assertDontSee('Persona Artistica');
        $this->get(route('casting.show', $audition))->assertNotFound();
        $this->get(route('casting.cv', $audition))->assertNotFound();
        $this->get(route('material.photo', $audition))->assertNotFound();
        $this->get(route('audition.received', $audition->reference))->assertNotFound();
        $this->post('/candidati', $this->payload($audition->email))->assertSessionHasErrors('email');

        $job = \Illuminate\Support\Facades\Queue::connection('self-tapes')->pop('self-tapes');
        $this->assertNotNull($job);
        $job->fire();
        $this->assertTrue($job->isDeleted());

        $this->actingAs($admin, 'web');
        $this->get(route('casting.confirm-delete', $audition))->assertOk()->assertSee('Riprendi eliminazione');
        $this->delete(route('casting.destroy', $audition), ['confirmation'=>'Persona Artistica'])
            ->assertRedirect(route('casting.index'));

        $this->assertModelMissing($audition);
        $disk->assertMissing([$video->file_path, $audition->photo_path, $audition->full_photo_path, $audition->cv_path, $directory.'/encoded.mp4']);
    }

    public function test_a_conversion_in_progress_does_not_publish_after_deletion_begins(): void
    {
        $audition = $this->audition();
        $video = $audition->videos()->firstOrFail();
        $disk = Storage::disk('local');
        $compression = Mockery::mock(SelfTapeCompression::class);
        $compression->shouldReceive('encode')->once()->andReturnUsing(function () use ($audition, $video, $disk): int {
            $disk->put('self-tape-processing/'.$video->id.'/encoded.mp4', 'Conversione di prova');
            Audition::whereKey($audition->id)->update(['deletion_requested_at'=>now()]);

            return 10;
        });
        $this->app->instance(SelfTapeCompression::class, $compression);

        $job = \Illuminate\Support\Facades\Queue::connection('self-tapes')->pop('self-tapes');
        $this->assertNotNull($job);
        $job->fire();

        $this->assertTrue($job->isDeleted());
        $this->assertSame($video->file_path, $video->fresh()->file_path);
        $this->assertSame([$video->file_path], $disk->allFiles('audition-videos'));
        $this->assertSame([], $disk->allFiles('self-tape-processing'));
    }
    public function test_captcha_is_required_expires_and_cannot_be_reused(): void {
        $missing=$this->payload();unset($missing['captcha_code']);$this->post('/candidati',$missing)->assertSessionHasErrors('captcha_code');
        $wrong=$this->payload();$wrong['captcha_code']=$wrong['captcha_code']==='000000'?'111111':'000000';$this->post('/candidati',$wrong)->assertSessionHasErrors('captcha_code');
        $expired=$this->payload();session()->put('audition_captchas.'.$expired['captcha_id'].'.expires',now()->timestamp-1);$this->post('/candidati',$expired)->assertSessionHasErrors('captcha_code');
        $valid=$this->payload();$this->post('/candidati',$valid)->assertRedirect();$again=$this->payload('altra@example.test');$again['captcha_id']=$valid['captcha_id'];$again['captcha_code']=$valid['captcha_code'];$this->post('/candidati',$again)->assertSessionHasErrors('captcha_code');$this->assertSame(1,Audition::count());
    }
    public function test_captcha_renewal_replaces_the_visual_challenge_and_has_no_audio(): void
    {
        $first = $this->payload();
        $renewed = $this->postJson(route('audition.captcha.renew'), ['captcha_id'=>$first['captcha_id']])
            ->assertOk()->assertJsonStructure(['id','image'])->assertJsonMissingPath('audio')->json();
        $this->assertNotSame($first['captcha_id'], $renewed['id']);
        $this->assertStringStartsWith('data:image/jpeg;base64,', $renewed['image']);
        $this->post('/candidati', $first)->assertSessionHasErrors('captcha_code');
        $this->post('/candidati', [...$first, 'captcha_id'=>$renewed['id']])->assertRedirect();

        $this->get('/candidati')->assertOk()->assertDontSee('<audio', false);
        $this->get('/candidati/captcha/'.$first['captcha_id'].'/audio')->assertNotFound();
        $this->get('/captcha/audition')->assertNotFound();
        $this->get('/captcha/api/audition')->assertNotFound();

        $otherSession = $this->payload('altra@example.test');
        session()->forget('audition_captchas');
        $this->post('/candidati', $otherSession)->assertSessionHasErrors('captcha_code');
    }
    public function test_ip_limit_counts_successful_complete_submissions_and_expires(): void {
        config(['casting.uploads_per_hour'=>1]);$missing=$this->payload();unset($missing['self_tape']);$this->post('/candidati',$missing)->assertSessionHasErrors('self_tape');$this->audition();
        $this->post('/candidati',$this->payload('altra@example.test'))->assertSessionHasErrors('upload_limit');$this->assertSame(1,Audition::count());
        $this->postJson('/candidati',[])->assertUnprocessable()->assertJsonValidationErrors('upload_limit')->assertJsonMissingValidationErrors('self_tape');
        $this->get('/candidati')->assertOk()->assertSee('È già stata ricevuta una candidatura da questa connessione');
        $this->travel(61)->minutes();$this->post('/candidati',$this->payload('altra@example.test'))->assertRedirect();$this->assertSame(2,Audition::count());
    }
    public function test_failed_attempts_have_a_separate_rate_limit_per_ip(): void
    {
        config(['casting.upload_attempts_per_quarter_hour'=>4]);
        $this->withServerVariables(['REMOTE_ADDR'=>'192.0.2.10']);
        for ($attempt = 0; $attempt < 4; $attempt++) {
            $this->postJson('/candidati', [])->assertUnprocessable();
        }
        $this->withServerVariables(['REMOTE_ADDR'=>'::ffff:192.0.2.10']);
        $this->postJson('/candidati', [], ['X-Forwarded-For'=>'192.0.2.11'])
            ->assertTooManyRequests()->assertHeader('Retry-After');
        $this->withServerVariables(['REMOTE_ADDR'=>'192.0.2.11']);
        $this->postJson('/candidati', [])->assertUnprocessable();

        $this->travel(16)->minutes();
        $this->withServerVariables(['REMOTE_ADDR'=>'192.0.2.10']);
        $this->postJson('/candidati', [])->assertUnprocessable();
        $this->assertSame(0, Audition::count());
    }
    public function test_video_size_limit_is_100_megabytes(): void {
        config(['casting.uploads_per_hour'=>1]);
        /** Le dimensioni sono simulate: la prova non genera né invia file da cento megabyte. */
        $large=$this->payload();$large['self_tape']=UploadedFile::fake()->create('provino.mp4',97657,'video/mp4');
        $this->post('/candidati',$large)->assertSessionHasErrors('self_tape');$this->assertSame(0,Audition::count());
        $this->audition();$this->assertSame(1,Audition::firstOrFail()->videos()->count());
    }
    public function test_server_rejects_a_self_tape_longer_than_five_minutes(): void
    {
        $temporaryFile = tempnam(sys_get_temp_dir(), 'nero-video-');
        $this->temporaryUploads[] = $temporaryFile;
        (new \Symfony\Component\Process\Process([
            config('casting.ffmpeg_binary'), '-y', '-hide_banner', '-loglevel', 'error',
            '-f', 'lavfi', '-i', 'color=c=black:s=32x32:r=1', '-t', '301', '-c:v', 'mpeg4', '-f', 'mp4', $temporaryFile,
        ]))->mustRun();

        $data = $this->payload();
        $data['self_tape'] = new UploadedFile($temporaryFile, 'provino-lungo.mp4', null, UPLOAD_ERR_OK, true);
        $this->post('/candidati', $data)->assertSessionHasErrors(['self_tape'=>'Il self-tape deve durare al massimo 5 minuti.']);
        $this->assertSame(0, Audition::count());
    }
    public function test_self_tape_requires_a_video_track_and_rejects_an_audio_cover(): void
    {
        $temporaryFile = tempnam(sys_get_temp_dir(), 'nero-audio-');
        $this->temporaryUploads[] = $temporaryFile;
        (new \Symfony\Component\Process\Process([
            config('casting.ffmpeg_binary'), '-y', '-hide_banner', '-loglevel', 'error',
            '-f', 'lavfi', '-i', 'sine=frequency=440:duration=1',
            '-i', public_path('assets/nero/hero.jpg'), '-map', '0:a:0', '-map', '1:v:0',
            '-c:a', 'aac', '-c:v', 'copy', '-disposition:v:0', 'attached_pic', '-f', 'mp4', $temporaryFile,
        ]))->mustRun();

        $file = new UploadedFile($temporaryFile, 'audio-con-copertina.mp4', null, UPLOAD_ERR_OK, true);
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->expectExceptionMessage('Il file deve contenere un video leggibile.');
        app(\App\Services\SelfTape::class)->duration($file);
    }
    public function test_submission_queues_conversion_to_mp4_and_preserves_compatible_audio(): void
    {
        $temporaryFile = tempnam(sys_get_temp_dir(), 'nero-mov-');
        $this->temporaryUploads[] = $temporaryFile;
        $ffmpeg = config('casting.ffmpeg_binary');
        (new \Symfony\Component\Process\Process([
            $ffmpeg, '-y', '-hide_banner', '-loglevel', 'error',
            '-f', 'lavfi', '-i', 'testsrc2=size=640x360:rate=24:duration=4',
            '-f', 'lavfi', '-i', 'sine=frequency=440:duration=4',
            '-c:v', 'libx264', '-crf', '5', '-preset', 'ultrafast', '-threads', '2',
            '-c:a', 'aac', '-f', 'mov', $temporaryFile,
        ]))->mustRun();

        $audioHash = fn (string $path) => (new \Symfony\Component\Process\Process([
            $ffmpeg, '-hide_banner', '-loglevel', 'error', '-i', $path,
            '-map', '0:a:0', '-c:a', 'copy', '-f', 'streamhash', '-',
        ]))->mustRun()->getOutput();
        $originalAudioHash = $audioHash($temporaryFile);

        $payload = $this->payload();
        $payload['self_tape'] = new UploadedFile($temporaryFile, 'provino.mov', null, UPLOAD_ERR_OK, true);
        $this->post('/candidati', $payload)->assertRedirect();
        $audition = Audition::firstOrFail();
        $video = $audition->videos()->firstOrFail();
        $this->assertSame(\App\Models\AuditionVideo::QUEUED, $video->compression_status);
        $this->get(route('audition.received', $audition->reference))->assertOk()->assertSee('Il self-tape è in elaborazione');
        $this->actingAs($this->operator())->get(route('casting.show', $audition))->assertOk()
            ->assertSee('Self-tape in elaborazione.')->assertDontSee(route('casting.videos.watch', $video));
        $originalPath = $video->file_path;
        $originalBytes = $video->bytes;

        $job = \Illuminate\Support\Facades\Queue::connection('self-tapes')->pop('self-tapes');
        $this->assertNotNull($job);
        $job->fire();
        $this->assertTrue($job->isDeleted());

        $video->refresh();
        $this->assertSame(\App\Models\AuditionVideo::COMPRESSED, $video->compression_status);
        $this->assertSame('video/mp4', $video->file_mime);
        $this->assertSame($originalBytes, $video->original_bytes);
        $this->assertGreaterThanOrEqual($originalBytes * .50, $video->bytes);
        $this->assertLessThanOrEqual($originalBytes * .70, $video->bytes);
        $this->assertNull($video->compression_source_path);
        $disk = Storage::disk('local');
        $disk->assertMissing($originalPath);
        $disk->assertExists($video->file_path);

        $this->assertSame($originalAudioHash, $audioHash($disk->path($video->file_path)));
        $media = app(\App\Services\SelfTape::class)->inspect($disk->path($video->file_path), 'video/mp4');
        $this->assertSame([640,360], [$media['width'],$media['height']]);
        $this->get(route('casting.show', $audition))->assertOk()->assertSee('MP4 pronto');
        $this->get(route('casting.videos.watch', $video), ['Range'=>'bytes=0-15'])->assertStatus(206);
    }

    public function test_duplicate_submission_returns_the_same_receipt_and_discards_new_attachments(): void
    {
        $audition = $this->audition();
        $this->withServerVariables(['REMOTE_ADDR'=>'127.0.0.2'])
            ->post('/candidati', $this->payload())
            ->assertRedirect(route('audition.received', $audition->reference));

        $this->assertDatabaseCount('auditions', 1);
        $this->assertDatabaseCount('audition_videos', 1);
        $this->assertDatabaseCount('jobs', 1);
        $this->assertCount(1, Storage::disk('local')->allFiles('audition-videos'));
        $this->assertCount(2, Storage::disk('local')->allFiles('candidate-photos'));
        $this->assertCount(1, Storage::disk('local')->allFiles('candidate-cvs'));
    }

    public function test_compression_keeps_a_small_mp4_and_leaves_no_temporary_files(): void
    {
        $temporaryFile = tempnam(sys_get_temp_dir(), 'nero-small-mp4-');
        $this->temporaryUploads[] = $temporaryFile;
        (new \Symfony\Component\Process\Process([
            config('casting.ffmpeg_binary'), '-y', '-hide_banner', '-loglevel', 'error',
            '-f', 'lavfi', '-i', 'color=c=black:size=64x36:rate=12:duration=2',
            '-c:v', 'libx264', '-f', 'mp4', $temporaryFile,
        ]))->mustRun();
        $payload = $this->payload();
        $payload['self_tape'] = new UploadedFile($temporaryFile, 'provino.mp4', null, UPLOAD_ERR_OK, true);
        $this->post('/candidati', $payload)->assertRedirect();
        $video = Audition::firstOrFail()->videos()->firstOrFail();
        $path = $video->file_path;

        $job = \Illuminate\Support\Facades\Queue::connection('self-tapes')->pop('self-tapes');
        $this->assertNotNull($job);
        $job->fire();
        $this->assertSame(\App\Models\AuditionVideo::ORIGINAL, $video->fresh()->compression_status);
        $this->assertSame($path, $video->fresh()->file_path);
        $this->assertSame([], Storage::disk('local')->allFiles('self-tape-processing'));
    }

    public function test_webm_is_converted_to_mp4_with_compatible_audio(): void
    {
        $temporaryFile = tempnam(sys_get_temp_dir(), 'nero-webm-');
        $this->temporaryUploads[] = $temporaryFile;
        (new \Symfony\Component\Process\Process([
            config('casting.ffmpeg_binary'), '-y', '-hide_banner', '-loglevel', 'error',
            '-f', 'lavfi', '-i', 'testsrc2=size=320x180:rate=12:duration=2',
            '-f', 'lavfi', '-i', 'sine=frequency=440:duration=2',
            '-c:v', 'libvpx-vp9', '-deadline', 'realtime', '-threads', '2',
            '-c:a', 'libopus', '-f', 'webm', $temporaryFile,
        ]))->mustRun();
        $payload = $this->payload();
        $payload['self_tape'] = new UploadedFile($temporaryFile, 'provino.webm', null, UPLOAD_ERR_OK, true);
        $this->post('/candidati', $payload)->assertRedirect();
        $video = Audition::firstOrFail()->videos()->firstOrFail();

        $job = \Illuminate\Support\Facades\Queue::connection('self-tapes')->pop('self-tapes');
        $this->assertNotNull($job);
        $job->fire();
        $video->refresh();
        $this->assertSame(\App\Models\AuditionVideo::COMPRESSED, $video->compression_status);
        $this->assertSame('video/mp4', $video->file_mime);
        $media = app(\App\Services\SelfTape::class)->inspect(Storage::disk('local')->path($video->file_path), 'video/mp4');
        $this->assertSame('aac', $media['audio']);
        $this->assertSame([320,180], [$media['width'],$media['height']]);
    }
    public function test_removing_legacy_candidate_access_preserves_applications_media_and_production(): void {
        $audition=$this->audition();$operator=$this->operator(true);$legacy=Candidate::create(['email'=>'legacy@example.test','password'=>Str::random(28)]);
        DB::table('auditions')->where('id',$audition->id)->update(['candidate_id'=>$legacy->id]);
        DB::table('candidate_access_tokens')->insert(['candidate_id'=>$legacy->id,'token_hash'=>hash('sha256',Str::random(48)),'expires_at'=>now()->addDay(),'created_at'=>now(),'updated_at'=>now()]);
        DB::table('candidate_messages')->insert(['candidate_id'=>$legacy->id,'audition_id'=>$audition->id,'subject'=>'Accesso candidato','payload'=>Crypt::encryptString('{}'),'delivery'=>'local','created_at'=>now(),'updated_at'=>now()]);
        $paths=$audition->videos->pluck('file_path')->all();$migration=require database_path('migrations/2026_11_04_120000_remove_candidate_access.php');$migration->up();
        $this->assertSame(0,Candidate::count());$this->assertSame(0,DB::table('candidate_access_tokens')->count());$this->assertSame(0,CandidateMessage::count());
        $this->assertSame('Persona',$audition->fresh()->first_name);$this->assertNull($audition->fresh()->getAttribute('candidate_id'));$this->assertSame(1,$audition->fresh()->videos()->count());$this->assertTrue($operator->fresh()->is_casting_operator);foreach($paths as $path)Storage::disk('local')->assertExists($path);
    }
}

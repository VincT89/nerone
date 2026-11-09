<?php
namespace Tests\Feature;
use App\Models\{Audition,AuditionComment,AuditionVote,Candidate,CandidateMessage,Selection,User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{Auth,Crypt,DB,Hash,Storage};
use Illuminate\Support\Str;
use Tests\TestCase;
class CandidateWorkflowTest extends TestCase {
    use RefreshDatabase;
    private array $temporaryUploads=[];
    protected function setUp(): void {$this->mockConsoleOutput=false;parent::setUp();Storage::fake('local');config(['casting.uploads_per_hour'=>100]);}
    /** Le migrazioni di prova non devono mai raggiungere il database delle candidature locali. */
    protected function beforeRefreshingDatabase(){if(config('database.default')!=='sqlite' || DB::connection()->getDatabaseName()!==':memory:')throw new \RuntimeException('Le prove richiedono SQLite in memoria.');}
    protected function tearDown(): void {foreach($this->temporaryUploads as $path)if(is_file($path))unlink($path);parent::tearDown();}
    private function upload(string $content,string $name): UploadedFile {$path=tempnam(sys_get_temp_dir(),'nerone-test-');file_put_contents($path,$content);$this->temporaryUploads[]=$path;return new UploadedFile($path,$name,null,UPLOAD_ERR_OK,true);}
    private function captcha(): array {$this->withSession([]);$challenge=app(\App\Services\LocalCaptcha::class)->issue();return ['captcha_id'=>$challenge['id'],'captcha_code'=>Crypt::decryptString(session('audition_captcha.'.$challenge['id'].'.cipher'))];}
    private function payload(string $email='persona@example.test',int $count=3): array {$videos=[];for($i=0;$i<$count;$i++)$videos[]=$this->upload(file_get_contents(public_path('assets/nero/trailer-nero.mp4')),'provino.mp4');return [...$this->captcha(),'selection_id'=>Selection::firstOrFail()->id,'first_name'=>'Persona','last_name'=>'Artistica','email'=>$email,'phone'=>'0000000000','city'=>'Roma','discipline'=>'Canto','adult'=>'1','consent'=>'1','videos'=>$videos];}
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
    public function test_intake_requires_exactly_three_real_videos(): void {
        $this->get('/candidati')->assertOk()->assertSee('videos[0]',false)->assertSee('videos[1]',false)->assertSee('videos[2]',false)->assertDontSee('area personale');
        $this->post('/candidati',$this->payload(count:2))->assertSessionHasErrors(['videos','videos.2']);
        $this->post('/candidati',$this->payload(count:4))->assertSessionHasErrors('videos');
        $bad=$this->payload();$bad['videos'][0]=$this->upload(file_get_contents(public_path('assets/nero/hero.jpg')),'falso.mp4');$this->post('/candidati',$bad)->assertSessionHasErrors('videos.0');
        $audition=$this->audition();$this->assertSame(3,$audition->videos()->count());foreach($audition->videos as $video){Storage::disk('local')->assertExists($video->file_path);$this->assertSame('video/mp4',$video->file_mime);}
        $this->assertSame(0,CandidateMessage::count());$this->assertSame(0,Candidate::count());$this->assertNull(config('auth.guards.candidate'));
    }
    public function test_repeat_intake_keeps_the_original_files_and_contact(): void {
        $audition=$this->audition();$paths=$audition->videos->pluck('file_path')->all();$this->post('/candidati',[...$this->payload(),'first_name'=>'Altro'])->assertRedirect();
        $this->assertSame(1,Audition::count());$this->assertSame(3,$audition->fresh()->videos()->count());$this->assertSame($paths,$audition->fresh()->videos->pluck('file_path')->all());$this->assertSame('Persona',$audition->fresh()->first_name);
    }
    public function test_photo_and_video_are_private_and_support_partial_streaming(): void {
        $this->post('/candidati',[...$this->payload(),'photo'=>$this->upload(file_get_contents(public_path('assets/nero/hero.jpg')),'foto.jpg')])->assertRedirect();$audition=Audition::firstOrFail();$video=$audition->videos->first();
        $this->get(route('material.photo',$audition))->assertNotFound();$this->get(route('casting.videos.watch',$video))->assertRedirect(route('login'));
        $this->withSession(['login_candidate_'.sha1(\Illuminate\Auth\SessionGuard::class)=>123])->get(route('material.photo',$audition))->assertNotFound();
        $this->actingAs($this->operator(),'web');$this->get(route('material.photo',$audition))->assertOk()->assertHeader('Cache-Control','no-store, private');
        $this->get(route('casting.videos.watch',$video),['Range'=>'bytes=0-15'])->assertStatus(206)->assertHeader('Content-Length','16');
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
        $this->get('/casting?status=Archiviato')->assertOk()->assertDontSee('Persona Artistica');
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
    public function test_deleting_an_audition_removes_all_three_files_and_reviews(): void {
        $audition=$this->audition();$paths=$audition->videos->pluck('file_path')->all();$operator=$this->operator();$audition->comments()->create(['user_id'=>$operator->id,'body'=>'Valutazione']);$audition->votes()->create(['user_id'=>$operator->id,'color'=>'green']);
        $this->actingAs($operator,'web')->delete(route('casting.destroy',$audition),['confirmation'=>'Persona Artistica'])->assertRedirect();$this->assertSame(0,Audition::count());$this->assertSame(0,AuditionVote::count());$this->assertSame(0,AuditionComment::count());foreach($paths as $path)Storage::disk('local')->assertMissing($path);
    }
    public function test_captcha_is_required_expires_and_cannot_be_reused(): void {
        $missing=$this->payload();unset($missing['captcha_code']);$this->post('/candidati',$missing)->assertSessionHasErrors('captcha_code');
        $wrong=$this->payload();$wrong['captcha_code']=$wrong['captcha_code']==='000000'?'111111':'000000';$this->post('/candidati',$wrong)->assertSessionHasErrors('captcha_code');
        $expired=$this->payload();session()->put('audition_captcha.'.$expired['captcha_id'].'.expires',time()-1);$this->post('/candidati',$expired)->assertSessionHasErrors('captcha_code');
        $valid=$this->payload();$this->post('/candidati',$valid)->assertRedirect();$again=$this->payload('altra@example.test');$again['captcha_id']=$valid['captcha_id'];$again['captcha_code']=$valid['captcha_code'];$this->post('/candidati',$again)->assertSessionHasErrors('captcha_code');$this->assertSame(1,Audition::count());
    }
    public function test_captcha_image_and_audio_require_the_issuing_session(): void {
        $fields=$this->captcha();$image=route('audition.captcha.image',$fields['captcha_id']);$audio=route('audition.captcha.audio',$fields['captcha_id']);
        $response=$this->get($image)->assertOk()->assertHeader('Content-Type','image/png');$this->assertStringStartsWith("\x89PNG",$response->getContent());
        $response=$this->get($audio)->assertOk()->assertHeader('Content-Type','audio/wav');$this->assertStringStartsWith('RIFF',$response->getContent());
        session()->forget('audition_captcha');$this->get($image)->assertNotFound();$this->get($audio)->assertNotFound();
    }
    public function test_ip_limit_counts_successful_complete_submissions_and_expires(): void {
        config(['casting.uploads_per_hour'=>1]);$this->post('/candidati',$this->payload(count:2))->assertSessionHasErrors('videos');$this->audition();
        $this->post('/candidati',$this->payload('altra@example.test'))->assertSessionHasErrors('upload_limit');$this->assertSame(1,Audition::count());
        $this->travel(61)->minutes();$this->post('/candidati',$this->payload('altra@example.test'))->assertRedirect();$this->assertSame(2,Audition::count());
    }
    public function test_video_size_limit_is_100_megabytes_for_the_three_files_together(): void {
        config(['casting.uploads_per_hour'=>1]);
        /** Le dimensioni sono simulate: la prova non genera né invia file da cento megabyte. */
        $large=$this->payload();$large['videos']=[UploadedFile::fake()->create('uno.mp4',40960,'video/mp4'),UploadedFile::fake()->create('due.mp4',40960,'video/mp4'),UploadedFile::fake()->create('tre.mp4',20481,'video/mp4')];
        $this->post('/candidati',$large)->assertSessionHasErrors('videos');$this->assertSame(0,Audition::count());
        $boundary=$this->payload();$boundary['videos']=[UploadedFile::fake()->create('uno.mp4',61440,'video/mp4'),UploadedFile::fake()->create('due.mp4',20480,'video/mp4'),UploadedFile::fake()->create('tre.mp4',20480,'video/mp4')];
        $this->post('/candidati',$boundary)->assertRedirect();$this->assertSame(3,Audition::firstOrFail()->videos()->count());
    }
    public function test_removing_legacy_candidate_access_preserves_applications_media_and_production(): void {
        $audition=$this->audition();$operator=$this->operator(true);$legacy=Candidate::create(['email'=>'legacy@example.test','password'=>Str::random(28)]);
        DB::table('auditions')->where('id',$audition->id)->update(['candidate_id'=>$legacy->id]);
        DB::table('candidate_access_tokens')->insert(['candidate_id'=>$legacy->id,'token_hash'=>hash('sha256',Str::random(48)),'expires_at'=>now()->addDay(),'created_at'=>now(),'updated_at'=>now()]);
        DB::table('candidate_messages')->insert(['candidate_id'=>$legacy->id,'audition_id'=>$audition->id,'subject'=>'Accesso candidato','payload'=>Crypt::encryptString('{}'),'delivery'=>'local','created_at'=>now(),'updated_at'=>now()]);
        $paths=$audition->videos->pluck('file_path')->all();$migration=require database_path('migrations/2026_11_04_120000_remove_candidate_access.php');$migration->up();
        $this->assertSame(0,Candidate::count());$this->assertSame(0,DB::table('candidate_access_tokens')->count());$this->assertSame(0,CandidateMessage::count());
        $this->assertSame('Persona',$audition->fresh()->first_name);$this->assertNull($audition->fresh()->getAttribute('candidate_id'));$this->assertSame(3,$audition->fresh()->videos()->count());$this->assertTrue($operator->fresh()->is_casting_operator);foreach($paths as $path)Storage::disk('local')->assertExists($path);
    }
}

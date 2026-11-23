<?php

namespace Tests\Feature;

use App\Models\Selection;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class SiteAccessTest extends TestCase
{
    use RefreshDatabase;

    private string $sitePassword;

    protected function beforeRefreshingDatabase(): void
    {
        if (config('database.default') !== 'sqlite' || DB::connection()->getDatabaseName() !== ':memory:') {
            throw new RuntimeException('Le prove richiedono SQLite in memoria.');
        }
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->sitePassword = Str::random(32);
        config(['casting.site_access_password' => $this->sitePassword]);
        $this->travelTo(CarbonImmutable::parse('2026-11-23 12:00', 'Europe/Rome'));
    }

    public function test_public_pages_require_the_password_even_via_direct_urls(): void
    {
        $this->app->instance('env', 'production');

        foreach (['/', '/candidati', '/ricevuto/'.Str::uuid()] as $path) {
            $response = $this->get($path)->assertOk()->assertViewIs('site-access')
                ->assertSee('data-site-access-form', false)
                ->assertDontSee('data-entry-video', false)
                ->assertDontSee('name="self_tape"', false)
                ->assertDontSee($this->sitePassword)
                ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
            $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        }

        $this->head('/candidati')->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->get('/accesso-sito')->assertRedirect('/');
    }

    public function test_locked_submissions_and_captcha_requests_do_not_reach_the_intake(): void
    {
        Queue::fake();

        $this->post('/candidati')->assertForbidden()->assertViewIs('site-access');
        foreach (['/candidati', '/candidati/captcha'] as $path) {
            $this->postJson($path)->assertForbidden()
                ->assertJsonPath('message', 'Inserisci la password di accesso al sito.');
        }

        $this->assertDatabaseCount('auditions', 0);
        Queue::assertNothingPushed();
    }

    public function test_wrong_password_stays_private_and_does_not_unlock_the_site(): void
    {
        $attempt = Str::random(32);
        $this->from('/')->post('/accesso-sito', ['password' => $attempt])
            ->assertRedirect('/')->assertSessionHasErrors('password')
            ->assertSessionMissing('site_access_granted')
            ->assertSessionMissing('_old_input.password');
        $this->get('/')->assertSessionHasErrors('password')->assertViewIs('site-access')->assertSee('Password non corretta.')
            ->assertDontSee($attempt)->assertDontSee($this->sitePassword);
    }

    public function test_a_correct_password_unlocks_the_site_but_does_not_grant_a_production_account(): void
    {
        $this->post('/accesso-sito', ['password' => $this->sitePassword])
            ->assertRedirect(route('home'))->assertSessionHasNoErrors();
        $grant = session('site_access_granted');
        $this->assertIsString($grant);
        $this->assertNotSame($this->sitePassword, $grant);
        $this->assertSame(64, strlen($grant));
        $this->assertGuest();
        $this->get('/')->assertOk()->assertViewIs('home')->assertSee('data-entry-video', false);
        $this->get('/candidati')->assertOk()->assertViewIs('apply')->assertSee('name="self_tape"', false);
        $this->get('/casting')->assertRedirect(route('login'));
    }

    public function test_changing_the_password_revokes_existing_site_access(): void
    {
        $this->post('/accesso-sito', ['password' => $this->sitePassword])->assertRedirect();
        $this->get('/')->assertViewIs('home');

        config(['casting.site_access_password' => Str::random(32)]);
        $this->get('/')->assertViewIs('site-access');
        $this->get('/candidati')->assertViewIs('site-access');
    }

    public function test_the_session_grant_cannot_be_forged_with_the_password_or_another_string(): void
    {
        foreach ([$this->sitePassword, Str::random(64), true, ['granted']] as $grant) {
            $this->withSession(['site_access_granted' => $grant])->get('/')
                ->assertOk()->assertViewIs('site-access');
        }
    }

    public function test_emptying_the_setting_opens_the_site_without_changing_the_audition_period(): void
    {
        $selection = Selection::firstOrFail();
        $selection->update(['is_open' => false]);
        config(['casting.site_access_password' => '']);

        $this->get('/')->assertOk()->assertViewIs('home');
        $this->get('/candidati')->assertOk()->assertViewIs('apply')->assertViewHas('isOpen', false);

        $selection->update(['is_open' => true]);
        $this->get('/candidati')->assertOk()->assertViewHas('isOpen', true);
    }

    public function test_login_and_legal_pages_remain_available_while_the_site_is_locked(): void
    {
        foreach (['/login', '/privacy', '/cookie'] as $path) {
            $this->get($path)->assertOk()->assertDontSee('data-site-access-form', false)
                ->assertDontSee('>Candidati</a>', false);
        }
        $admin = User::factory()->create(['is_casting_operator' => true, 'is_casting_admin' => true]);
        $this->actingAs($admin)->get('/casting')->assertOk();
        $this->get('/casting/impostazioni')->assertOk()->assertDontSee('Pubblicazione sito');
        $this->get('/')->assertViewIs('site-access');
    }

    public function test_the_removed_publication_settings_are_not_exposed(): void
    {
        $admin = User::factory()->create(['is_casting_operator' => true, 'is_casting_admin' => true]);
        $this->actingAs($admin)->get('/casting/pubblicazione')->assertNotFound();
        $this->patch('/casting/pubblicazione', ['mode' => 'public'])->assertNotFound();
    }

    public function test_password_attempts_are_limited_and_resume_after_a_minute(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->from('/')->post('/accesso-sito', ['password' => 'invalid'])
                ->assertRedirect('/')->assertSessionHasErrors('password');
        }

        $this->post('/accesso-sito', ['password' => $this->sitePassword])
            ->assertStatus(429)->assertSessionMissing('site_access_granted');
        $this->travel(61)->seconds();
        $this->post('/accesso-sito', ['password' => $this->sitePassword])->assertRedirect(route('home'));
        $this->get('/')->assertViewIs('home');
    }

    public function test_missing_or_invalid_password_inputs_are_rejected(): void
    {
        foreach ([null, ['invalid'], str_repeat('a', 257)] as $password) {
            $this->postJson('/accesso-sito', ['password' => $password])
                ->assertUnprocessable()->assertJsonValidationErrors('password');
        }
        $this->get('/')->assertViewIs('site-access');
    }
}

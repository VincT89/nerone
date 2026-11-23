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
        $this->followingRedirects()->from('/')->post('/accesso-sito', ['password' => $attempt])
            ->assertOk()->assertViewIs('site-access')->assertSee('Password non corretta.')
            ->assertDontSee($attempt)->assertDontSee($this->sitePassword)
            ->assertSessionMissing('site_access_granted')->assertSessionMissing('_old_input.password');
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
        $this->withHeader('Referer', route('home'))->get('/candidati')
            ->assertOk()->assertViewIs('apply')->assertSee('name="self_tape"', false);
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

    public function test_new_direct_visits_require_the_password_again_in_the_same_session(): void
    {
        foreach (['/', '/candidati'] as $path) {
            $this->post('/accesso-sito', ['password' => $this->sitePassword])->assertRedirect(route('home'));
            $this->get('/')->assertViewIs('home');
            $this->get($path)->assertOk()->assertViewIs('site-access')
                ->assertSessionMissing('site_access_granted');
        }
    }

    public function test_internal_navigation_remains_available_for_the_current_visit(): void
    {
        $this->post('/accesso-sito', ['password' => $this->sitePassword])->assertRedirect();
        $this->get('/')->assertViewIs('home')->assertHeader('X-Robots-Tag', 'noindex, nofollow');

        $previous = '/';
        foreach (['/candidati', '/privacy', '/cookie', '/'] as $path) {
            $this->withHeaders(['Referer' => url($previous), 'Sec-Fetch-Site' => 'same-origin'])->get($path)
                ->assertOk()->assertDontSee('data-site-access-form', false);
            $previous = $path;
        }

        $this->withHeader('Referer', route('home'))->get('/')->assertViewIs('site-access');
    }

    public function test_returning_from_another_site_starts_a_new_visit(): void
    {
        $this->post('/accesso-sito', ['password' => $this->sitePassword])->assertRedirect();
        $this->get('/')->assertViewIs('home');
        $this->withHeaders(['Referer' => 'https://example.test/', 'Sec-Fetch-Site' => 'cross-site'])->get('/candidati')
            ->assertViewIs('site-access')->assertSessionMissing('site_access_granted');
    }

    public function test_legal_pages_do_not_keep_an_old_visit_unlocked_when_opened_directly(): void
    {
        $this->post('/accesso-sito', ['password' => $this->sitePassword])->assertRedirect();
        $this->get('/')->assertViewIs('home');
        $this->get('/privacy')->assertOk()->assertSessionMissing('site_access_granted');
        $this->withHeader('Referer', route('privacy'))->get('/')->assertViewIs('site-access');
    }

    public function test_reloading_or_restoring_a_page_requires_a_new_password(): void
    {
        $this->post('/accesso-sito', ['password' => $this->sitePassword])->assertRedirect();
        $this->get('/')->assertViewIs('home');
        $this->withHeader('Referer', route('home'))->get('/')->assertViewIs('site-access');

        $this->post('/accesso-sito', ['password' => $this->sitePassword])->assertRedirect();
        $this->get('/')->assertViewIs('home');
        $this->withHeader('Referer', route('home'))->get('/candidati?_site_entry=1')->assertViewIs('site-access');
    }

    public function test_a_reload_is_locked_even_if_the_browser_repeats_the_previous_page_referrer(): void
    {
        foreach (['max-age=0', 'no-cache'] as $cacheControl) {
            $this->post('/accesso-sito', ['password' => $this->sitePassword])->assertRedirect();
            $this->get('/')->assertViewIs('home');
            $this->withHeaders(['Referer' => route('privacy'), 'Sec-Fetch-Site' => 'same-origin', 'Cache-Control' => $cacheControl])
                ->get('/')->assertViewIs('site-access');
        }
    }

    public function test_intake_validation_redirects_keep_the_current_visit_unlocked(): void
    {
        $this->post('/accesso-sito', ['password' => $this->sitePassword])->assertRedirect();
        $this->get('/')->assertViewIs('home');
        $this->withHeader('Referer', route('home'))->get('/candidati')->assertViewIs('apply');

        $this->followingRedirects()->from('/candidati')->post('/candidati', [])
            ->assertOk()->assertViewIs('apply')->assertSee('name="self_tape"', false)
            ->assertSessionHas('site_access_granted');
    }

    public function test_navigation_headers_do_not_authenticate_a_visitor(): void
    {
        $this->withHeaders(['Referer' => route('home'), 'Sec-Fetch-Site' => 'same-origin'])->get('/candidati')
            ->assertOk()->assertViewIs('site-access');
    }

    public function test_a_missing_server_setting_keeps_the_site_locked(): void
    {
        config(['casting.site_access_password' => null]);
        $this->get('/')->assertOk()->assertViewIs('site-access');
        $this->from('/')->post('/accesso-sito', ['password' => $this->sitePassword])
            ->assertRedirect('/')->assertSessionHasErrors('password')
            ->assertSessionMissing('site_access_granted');
        $this->get('/login')->assertOk();
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

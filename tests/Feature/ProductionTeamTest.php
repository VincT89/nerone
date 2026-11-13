<?php

namespace Tests\Feature;

use App\Models\Audition;
use App\Models\User;
use Database\Seeders\CastingAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class ProductionTeamTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        if (config('database.default') !== 'sqlite' || DB::connection()->getDatabaseName() !== ':memory:') {
            throw new RuntimeException('Le prove richiedono SQLite in memoria.');
        }
    }

    public function test_seeder_hashes_the_configured_password_and_leaves_existing_admins_untouched(): void
    {
        $password = Str::random(24).'a1';
        config(['casting.admin_password' => $password]);
        $this->seed(CastingAdminSeeder::class);

        $admin = User::where('email', 'admin@admin.it')->firstOrFail();
        $hash = $admin->password;
        $this->assertTrue($admin->is_casting_operator);
        $this->assertTrue($admin->is_casting_admin);
        $this->assertNotSame($password, $hash);
        $this->assertTrue(Hash::check($password, $hash));
        $this->post(route('login.enter'), ['email' => $admin->email, 'password' => $password])
            ->assertRedirect(route('casting.index'));
        $this->get(route('casting.team'))->assertOk()->assertSee($admin->email);

        config(['casting.admin_password' => null]);
        $this->seed(CastingAdminSeeder::class);
        $this->assertDatabaseCount('users', 1);
        $this->assertSame($hash, $admin->fresh()->password);
    }

    public function test_only_admins_can_assign_roles_and_the_last_admin_is_protected(): void
    {
        $admin = $this->operator(admin: true);
        $collaborator = $this->operator();
        $ordinaryUser = User::factory()->create();
        $this->actingAs($collaborator)->patch(route('casting.team.update', $collaborator), ['role' => 'admin'])
            ->assertForbidden();
        $this->delete(route('casting.team.destroy', $admin), ['confirmation' => '1'])->assertForbidden();

        $this->actingAs($admin)->patch(route('casting.team.update', $admin), ['role' => 'collaborator'])
            ->assertSessionHasErrors('account');
        $this->assertTrue($admin->fresh()->is_casting_admin);
        $this->get(route('casting.team'))->assertOk()->assertSee('Modifica')->assertSee('Elimina');
        $this->get(route('casting.team.edit', $admin))->assertOk()->assertSee('Deve restare almeno un amministratore');
        $this->patch(route('casting.team.update', $admin), [
            'name' => 'Amministratore aggiornato',
            'email' => 'aggiornato@example.test',
        ])->assertRedirect(route('casting.team'));
        $this->assertDatabaseHas('users', [
            'id' => $admin->id,
            'name' => 'Amministratore aggiornato',
            'email' => 'aggiornato@example.test',
            'is_casting_admin' => true,
        ]);
        $this->delete(route('casting.team.destroy', $admin), ['confirmation' => '1'])->assertForbidden();
        $this->patch(route('casting.team.update', $ordinaryUser), ['role' => 'admin'])->assertNotFound();
        $this->patch(route('casting.team.update', $collaborator), ['role' => 'owner'])->assertSessionHasErrors('role');
        $this->patch(route('casting.team.update', $collaborator), ['role' => 'admin'])
            ->assertRedirect(route('casting.team'));
        $this->assertTrue($collaborator->fresh()->is_casting_admin);
        $this->patch(route('casting.team.update', $collaborator), ['role' => 'collaborator'])
            ->assertRedirect(route('casting.team'));
        $this->assertFalse($collaborator->fresh()->is_casting_admin);

        $password = Str::random(24).'a1';
        $this->post(route('casting.team.store'), [
            'name' => 'Nuovo amministratore',
            'email' => 'produzione@example.test',
            'role' => 'admin',
            'password' => $password,
            'password_confirmation' => $password,
        ])->assertRedirect(route('casting.team'));
        $this->assertDatabaseHas('users', [
            'email' => 'produzione@example.test',
            'is_casting_operator' => true,
            'is_casting_admin' => true,
        ]);
    }

    public function test_deleting_an_account_removes_its_votes_and_comments(): void
    {
        $admin = $this->operator(admin: true);
        $collaborator = $this->operator();
        $audition = Audition::create([
            'reference' => Str::uuid()->toString(),
            'first_name' => 'Persona',
            'last_name' => 'Artistica',
            'email' => 'candidatura@example.test',
            'phone' => '0000000000',
            'city' => 'Roma',
            'discipline' => 'ensemble',
        ]);
        foreach ([$admin, $collaborator] as $user) {
            $audition->votes()->create(['user_id' => $user->id, 'color' => 'green']);
            $audition->comments()->create(['user_id' => $user->id, 'body' => 'Valutazione di prova']);
        }

        $this->actingAs($admin)->get(route('casting.team.confirm-delete', $collaborator))
            ->assertOk()->assertSee('tutti i suoi voti e commenti');
        $this->delete(route('casting.team.destroy', $collaborator))->assertSessionHasErrors('confirmation');
        $this->assertDatabaseHas('users', ['id' => $collaborator->id]);

        $this->delete(route('casting.team.destroy', $collaborator), ['confirmation' => '1'])
            ->assertRedirect(route('casting.team'));
        $this->assertDatabaseMissing('users', ['id' => $collaborator->id]);
        foreach (['audition_votes', 'audition_comments'] as $table) {
            $this->assertDatabaseCount($table, 1);
            $this->assertDatabaseHas($table, ['user_id' => $admin->id, 'audition_id' => $audition->id]);
        }
    }

    private function operator(bool $admin = false): User
    {
        return User::factory()->create(['is_casting_operator' => true, 'is_casting_admin' => $admin]);
    }
}

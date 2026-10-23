<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected bool $authenticateByDefault = false;

    public function test_registration_page_is_public_and_links_back_to_login(): void
    {
        $this->get(route('register'))
            ->assertOk()
            ->assertSee('Crear cuenta')
            ->assertSee('name="_token"', false)
            ->assertSee('Confirmar contraseña')
            ->assertSee('Iniciá sesión')
            ->assertDontSee('name="is_active"', false);
    }

    public function test_registration_creates_an_inactive_user_even_if_the_request_asks_for_activation(): void
    {
        $this->post(route('register.store'), [
            'name' => ' Persona de prueba ',
            'email' => ' NUEVA@EXAMPLE.TEST ',
            'password' => 'ClaveSegura!123',
            'password_confirmation' => 'ClaveSegura!123',
            'is_active' => true,
        ])->assertRedirect(route('login'))
            ->assertSessionHas('status', 'Tu cuenta se creó y está pendiente de activación. Vas a poder ingresar cuando esté habilitada.');

        $this->assertGuest();
        $user = User::query()->sole();
        $this->assertSame('Persona de prueba', $user->name);
        $this->assertSame('nueva@example.test', $user->email);
        $this->assertFalse($user->is_active);
        $this->assertTrue(Hash::check('ClaveSegura!123', $user->password));
    }

    public function test_account_can_log_in_only_after_database_activation(): void
    {
        $user = User::factory()->inactive()->create(['email' => 'pendiente@example.test', 'password' => 'ClaveSegura!123']);

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'ClaveSegura!123',
        ])->assertSessionHasErrors(['email' => 'El email o la contraseña no son correctos.']);
        $this->assertGuest();

        DB::table('users')->where('id', $user->id)->update(['is_active' => true]);

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'ClaveSegura!123',
        ])->assertRedirect('/');
        $this->assertAuthenticatedAs($user);
    }

    public function test_deactivated_user_with_an_open_session_is_logged_out(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        DB::table('users')->where('id', $user->id)->update(['is_active' => false]);
        $user->refresh();

        $this->get(route('home'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_invalid_and_duplicate_registrations_do_not_create_users(): void
    {
        User::factory()->create(['email' => 'existente@example.test']);

        $this->from(route('register'))->post(route('register.store'), [
            'name' => 'Otra persona',
            'email' => 'existente@example.test',
            'password' => 'corta',
            'password_confirmation' => 'distinta',
        ])->assertRedirect(route('register'))->assertSessionHasErrors(['email', 'password']);

        $this->assertDatabaseCount('users', 1);
    }

    public function test_weak_password_is_rejected_with_a_spanish_message(): void
    {
        $this->post(route('register.store'), [
            'name' => 'Persona de prueba',
            'email' => 'prueba@example.test',
            'password' => 'corta',
            'password_confirmation' => 'corta',
        ])->assertSessionHasErrors(['password' => 'La contraseña debe tener al menos 12 caracteres.']);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_authenticated_user_is_redirected_from_registration(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('register'))
            ->assertRedirect(route('home'));
    }

    public function test_registration_is_rate_limited(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post(route('register.store'), [])->assertSessionHasErrors(['name', 'email', 'password']);
        }

        $this->post(route('register.store'), [])->assertStatus(429);
    }

    public function test_migration_activates_existing_accounts_and_defaults_new_accounts_to_pending(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('is_active');
        });

        $existing = User::query()->create([
            'name' => 'Cuenta existente',
            'email' => 'existente@example.test',
            'password' => 'ClaveSegura!123',
        ]);

        $migration = require database_path('migrations/2026_10_22_235413_add_is_active_to_users_table.php');
        $migration->up();

        $this->assertTrue($existing->fresh()->is_active);

        $pending = User::query()->create([
            'name' => 'Cuenta posterior',
            'email' => 'posterior@example.test',
            'password' => 'ClaveSegura!123',
        ]);

        $this->assertFalse($pending->fresh()->is_active);
    }
}

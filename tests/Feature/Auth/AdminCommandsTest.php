<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminCommandsTest extends TestCase
{
    use RefreshDatabase;

    protected bool $authenticateByDefault = false;

    public function test_initial_admin_command_creates_the_first_account_with_hidden_password_input(): void
    {
        $this->artisan('adara:admin:create')
            ->expectsQuestion('Nombre de la administradora', 'Alejo')
            ->expectsQuestion('Email', 'alejo@example.test')
            ->expectsQuestion('Contraseña (mínimo 12 caracteres)', 'ClaveSegura!123')
            ->expectsQuestion('Confirmá la contraseña', 'ClaveSegura!123')
            ->expectsOutputToContain('La cuenta inicial se creó correctamente.')
            ->assertSuccessful();

        $user = User::query()->sole();
        $this->assertSame('alejo@example.test', $user->email);
        $this->assertTrue($user->is_active);
        $this->assertTrue(Hash::check('ClaveSegura!123', $user->password));
    }

    public function test_initial_admin_command_refuses_to_create_another_account(): void
    {
        User::factory()->create();

        $this->artisan('adara:admin:create')
            ->expectsOutputToContain('Ya existe una cuenta. No se creó ningún usuario.')
            ->assertFailed();

        $this->assertDatabaseCount('users', 1);
    }

    public function test_reset_command_updates_password_and_revokes_existing_sessions(): void
    {
        config(['session.driver' => 'database']);
        $user = User::factory()->create(['password' => 'Anterior!1234']);
        \DB::table('sessions')->insert([
            'id' => 'session-to-revoke',
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'test',
            'payload' => 'payload',
            'last_activity' => now()->timestamp,
        ]);

        $this->artisan('adara:admin:reset-password', ['email' => $user->email])
            ->expectsQuestion('Nueva contraseña (mínimo 12 caracteres)', 'NuevaClave!5678')
            ->expectsQuestion('Confirmá la nueva contraseña', 'NuevaClave!5678')
            ->expectsOutputToContain('La contraseña se actualizó y las sesiones anteriores quedaron cerradas.')
            ->assertSuccessful();

        $user->refresh();
        $this->assertTrue(Hash::check('NuevaClave!5678', $user->password));
        $this->assertDatabaseMissing('sessions', ['id' => 'session-to-revoke']);
    }
}

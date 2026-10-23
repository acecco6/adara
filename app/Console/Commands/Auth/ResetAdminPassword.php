<?php

namespace App\Console\Commands\Auth;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

#[Signature('adara:admin:reset-password {email? : Email de la cuenta que querés actualizar}')]
#[Description('Restablece localmente la contraseña de una cuenta de administración.')]
class ResetAdminPassword extends Command
{
    public function handle(): int
    {
        if (config('session.driver') !== 'database') {
            $this->components->error('Para revocar las sesiones al cambiar la contraseña, configurá SESSION_DRIVER=database.');

            return self::FAILURE;
        }

        $userCount = User::query()->count();

        if ($userCount === 0) {
            $this->components->error('No hay cuentas para restablecer.');

            return self::FAILURE;
        }

        $emailArgument = $this->argument('email');
        $email = is_string($emailArgument) ? trim($emailArgument) : '';

        if ($email === '' && $userCount === 1) {
            $user = User::query()->firstOrFail();
            $email = $user->email;
            $this->components->info('Se actualizará la cuenta '.$email.'.');
        } elseif ($email === '' && $this->input->isInteractive()) {
            $email = trim((string) $this->ask('Email de la cuenta que querés actualizar'));
        }

        if ($email === '') {
            $this->components->error('Indicá el email de la cuenta que querés actualizar.');

            return self::FAILURE;
        }

        $user = User::query()->where('email', mb_strtolower($email))->first();

        if ($user === null) {
            $this->components->error('No se encontró una cuenta con ese email.');

            return self::FAILURE;
        }

        if (! $this->input->isInteractive()) {
            $this->components->error('Ejecutá este comando en una terminal interactiva.');

            return self::FAILURE;
        }

        $password = (string) $this->secret('Nueva contraseña (mínimo 12 caracteres)');
        $passwordConfirmation = (string) $this->secret('Confirmá la nueva contraseña');
        $validator = Validator::make(['password' => $password], [
            'password' => ['required', 'string', Password::min(12)->mixedCase()->numbers()->symbols()],
        ], [
            'password.min' => 'La contraseña debe tener al menos 12 caracteres.',
            'password.mixed' => 'La contraseña debe combinar mayúsculas y minúsculas.',
            'password.numbers' => 'La contraseña debe incluir un número.',
            'password.symbols' => 'La contraseña debe incluir un símbolo.',
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->components->error($message);
            }

            return self::FAILURE;
        }

        if ($password === '' || ! hash_equals($password, $passwordConfirmation)) {
            $this->components->error('Las contraseñas no coinciden. No se cambió ninguna cuenta.');

            return self::FAILURE;
        }

        $sessionTable = (string) config('session.table', 'sessions');

        if (! preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $sessionTable) || ! Schema::hasTable($sessionTable)) {
            $this->components->error('No se pudo verificar la tabla de sesiones; la contraseña no se cambió.');

            return self::FAILURE;
        }

        try {
            DB::transaction(function () use ($user, $password, $sessionTable): void {
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();

                DB::table($sessionTable)->where('user_id', $user->getKey())->delete();
            });
        } catch (QueryException) {
            $this->components->error('No se pudo actualizar la contraseña ni invalidar las sesiones.');

            return self::FAILURE;
        }

        $this->components->info('La contraseña se actualizó y las sesiones anteriores quedaron cerradas.');

        return self::SUCCESS;
    }
}

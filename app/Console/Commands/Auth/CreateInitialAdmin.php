<?php

namespace App\Console\Commands\Auth;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

#[Signature('adara:admin:create')]
#[Description('Crea la cuenta inicial de administración de Adara.')]
class CreateInitialAdmin extends Command
{
    public function handle(): int
    {
        if (User::query()->exists()) {
            $this->components->error('Ya existe una cuenta. No se creó ningún usuario.');

            return self::FAILURE;
        }

        if (! $this->input->isInteractive()) {
            $this->components->error('Ejecutá este comando en una terminal interactiva.');

            return self::FAILURE;
        }

        $name = trim((string) $this->ask('Nombre de la administradora'));
        $email = mb_strtolower(trim((string) $this->ask('Email')));
        $password = (string) $this->secret('Contraseña (mínimo 12 caracteres)');
        $passwordConfirmation = (string) $this->secret('Confirmá la contraseña');

        $validator = Validator::make([
            'name' => $name,
            'email' => $email,
            'password' => $password,
        ], [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email:rfc', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', Password::min(12)->mixedCase()->numbers()->symbols()],
        ], [
            'name.required' => 'Ingresá un nombre.',
            'name.max' => 'El nombre no puede superar los 255 caracteres.',
            'email.required' => 'Ingresá un email.',
            'email.email' => 'Ingresá un email válido.',
            'email.max' => 'El email no puede superar los 255 caracteres.',
            'email.unique' => 'Ese email ya tiene una cuenta.',
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
            $this->components->error('Las contraseñas no coinciden. No se creó ningún usuario.');

            return self::FAILURE;
        }

        try {
            $user = new User([
                'name' => $name,
                'email' => $email,
                'password' => $password,
            ]);
            $user->is_active = true;
            $user->save();
        } catch (QueryException) {
            $this->components->error('No se pudo crear la cuenta. Revisá que el email no esté registrado.');

            return self::FAILURE;
        }

        $this->components->info('La cuenta inicial se creó correctamente.');

        return self::SUCCESS;
    }
}

<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    public function create(): View
    {
        return view('auth.register');
    }

    public function store(Request $request): RedirectResponse
    {
        if (is_string($request->input('name'))) {
            $request->merge(['name' => trim($request->input('name'))]);
        }

        if (is_string($request->input('email'))) {
            $request->merge(['email' => Str::lower(trim($request->input('email')))]);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email:rfc', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'max:255', 'confirmed', Password::min(12)->mixedCase()->numbers()->symbols()],
            'password_confirmation' => ['required', 'string'],
        ], [
            'name.required' => 'Ingresá tu nombre.',
            'name.max' => 'El nombre no puede superar los 255 caracteres.',
            'email.required' => 'Ingresá tu email.',
            'email.email' => 'Ingresá un email válido.',
            'email.max' => 'El email no puede superar los 255 caracteres.',
            'email.unique' => 'Ya existe una cuenta con ese email.',
            'password.required' => 'Ingresá una contraseña.',
            'password.max' => 'La contraseña no puede superar los 255 caracteres.',
            'password.confirmed' => 'Las contraseñas no coinciden.',
            'password.min' => 'La contraseña debe tener al menos 12 caracteres.',
            'password.mixed' => 'La contraseña debe combinar mayúsculas y minúsculas.',
            'password.numbers' => 'La contraseña debe incluir un número.',
            'password.symbols' => 'La contraseña debe incluir un símbolo.',
            'password_confirmation.required' => 'Confirmá la contraseña.',
        ]);

        try {
            $user = new User([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => $validated['password'],
            ]);
            $user->is_active = false;
            $user->save();
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'email' => 'Ya existe una cuenta con ese email.',
            ]);
        }

        return redirect()->route('login')->with('status', 'Tu cuenta se creó y está pendiente de activación. Vas a poder ingresar cuando esté habilitada.');
    }
}

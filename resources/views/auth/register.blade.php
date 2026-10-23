@extends('layouts.guest')

@section('title', 'Crear cuenta')

@section('content')
    <section class="w-full max-w-[480px] rounded-adara-dialog border border-adara-border bg-adara-surface p-6 shadow-adara-panel sm:p-9" aria-labelledby="register-title">
        <div class="mb-7 flex items-center gap-3">
            <span class="flex h-12 w-12 items-center justify-center rounded-[12px] bg-adara-primary font-display text-[27px] font-bold leading-none text-white" aria-hidden="true">a.</span>
            <div>
                <p class="font-display text-[25px] font-bold leading-none text-adara-ink">adara.</p>
                <p class="mt-1 text-[10px] font-semibold tracking-[0.12em] text-adara-readable-muted uppercase">Gestión de cosmética</p>
            </div>
        </div>

        <div class="mb-6">
            <p class="text-[11px] font-extrabold tracking-[0.16em] text-adara-primary uppercase">Acceso privado</p>
            <h1 id="register-title" class="mt-2 font-display text-[28px] font-semibold text-adara-ink">Crear cuenta</h1>
            <p class="mt-2 text-sm leading-6 text-adara-readable-muted">Completá tus datos. La cuenta quedará pendiente hasta que sea activada.</p>
        </div>

        <form method="POST" action="{{ route('register.store', absolute: false) }}" data-auth-form class="grid gap-4">
            @csrf

            <div>
                <label for="register-name" class="mb-1.5 block text-sm font-semibold">Nombre</label>
                <input id="register-name" name="name" type="text" value="{{ old('name') }}" autocomplete="name" maxlength="255" required autofocus
                    @if ($errors->has('name')) aria-invalid="true" aria-describedby="register-name-error" @else aria-invalid="false" @endif
                    class="min-h-12 w-full rounded-adara-control border border-adara-border bg-adara-surface px-4 py-3 text-sm text-adara-ink focus:border-adara-primary focus:outline-none focus:ring-2 focus:ring-adara-primary/20">
                @error('name')
                    <p id="register-name-error" role="alert" class="mt-1.5 text-sm font-medium text-adara-danger">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="register-email" class="mb-1.5 block text-sm font-semibold">Email</label>
                <input id="register-email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" inputmode="email" maxlength="255" required
                    @if ($errors->has('email')) aria-invalid="true" aria-describedby="register-email-error" @else aria-invalid="false" @endif
                    class="min-h-12 w-full rounded-adara-control border border-adara-border bg-adara-surface px-4 py-3 text-sm text-adara-ink focus:border-adara-primary focus:outline-none focus:ring-2 focus:ring-adara-primary/20">
                @error('email')
                    <p id="register-email-error" role="alert" class="mt-1.5 text-sm font-medium text-adara-danger">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="register-password" class="mb-1.5 block text-sm font-semibold">Contraseña</label>
                <input id="register-password" name="password" type="password" autocomplete="new-password" maxlength="255" required aria-describedby="register-password-hint{{ $errors->has('password') ? ' register-password-error' : '' }}"
                    @if ($errors->has('password')) aria-invalid="true" @else aria-invalid="false" @endif
                    class="min-h-12 w-full rounded-adara-control border border-adara-border bg-adara-surface px-4 py-3 text-sm text-adara-ink focus:border-adara-primary focus:outline-none focus:ring-2 focus:ring-adara-primary/20">
                <p id="register-password-hint" class="mt-1.5 text-xs leading-5 text-adara-readable-muted">Mínimo 12 caracteres, con mayúsculas, minúsculas, un número y un símbolo.</p>
                @error('password')
                    <p id="register-password-error" role="alert" class="mt-1.5 text-sm font-medium text-adara-danger">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="register-password-confirmation" class="mb-1.5 block text-sm font-semibold">Confirmar contraseña</label>
                <input id="register-password-confirmation" name="password_confirmation" type="password" autocomplete="new-password" maxlength="255" required
                    @if ($errors->has('password_confirmation')) aria-invalid="true" aria-describedby="register-password-confirmation-error" @else aria-invalid="false" @endif
                    class="min-h-12 w-full rounded-adara-control border border-adara-border bg-adara-surface px-4 py-3 text-sm text-adara-ink focus:border-adara-primary focus:outline-none focus:ring-2 focus:ring-adara-primary/20">
                @error('password_confirmation')
                    <p id="register-password-confirmation-error" role="alert" class="mt-1.5 text-sm font-medium text-adara-danger">{{ $message }}</p>
                @enderror
            </div>

            <button type="submit" data-auth-submit data-auth-loading-label="Creando cuenta…"
                class="mt-1 inline-flex min-h-12 items-center justify-center gap-2 rounded-adara-control bg-adara-primary px-5 py-3 text-sm font-semibold text-white transition-colors duration-150 hover:bg-adara-primary-hover focus-visible:outline-adara-primary disabled:cursor-wait disabled:opacity-75">
                <span data-auth-spinner class="hidden h-4 w-4 animate-spin rounded-full border-2 border-white/40 border-t-white motion-reduce:animate-none" aria-hidden="true"></span>
                <span data-auth-label aria-live="polite">Crear cuenta</span>
            </button>
        </form>

        <p class="mt-6 text-center text-sm text-adara-readable-muted">¿Ya tenés una cuenta? <a href="{{ route('login', absolute: false) }}" class="font-semibold text-adara-primary underline-offset-4 hover:underline focus-visible:outline-adara-primary">Iniciá sesión</a></p>
    </section>
@endsection

@extends('layouts.guest')

@section('title', 'Iniciar sesión')

@section('content')
    <section class="w-full max-w-[440px] rounded-adara-dialog border border-adara-border bg-adara-surface p-6 shadow-adara-panel sm:p-9" aria-labelledby="login-title">
        <div class="mb-8 flex items-center gap-3">
            <span class="flex h-12 w-12 items-center justify-center rounded-[12px] bg-adara-primary font-display text-[27px] font-bold leading-none text-white" aria-hidden="true">a.</span>
            <div>
                <p class="font-display text-[25px] font-bold leading-none text-adara-ink">adara.</p>
                <p class="mt-1 text-[10px] font-semibold tracking-[0.12em] text-adara-readable-muted uppercase">Gestión de cosmética</p>
            </div>
        </div>

        <div class="mb-6">
            <p class="text-[11px] font-extrabold tracking-[0.16em] text-adara-primary uppercase">Acceso privado</p>
            <h1 id="login-title" class="mt-2 font-display text-[28px] font-semibold text-adara-ink">Iniciar sesión</h1>
            <p class="mt-2 text-sm leading-6 text-adara-readable-muted">Ingresá con el email y la contraseña de tu cuenta.</p>
        </div>

        @if (session('status'))
            <p role="status" class="mb-4 rounded-adara-control bg-adara-success-bg px-4 py-3 text-sm font-medium text-adara-success">{{ session('status') }}</p>
        @endif

        <form method="POST" action="{{ route('login.store', absolute: false) }}" data-auth-form class="grid gap-5">
            @csrf

            <div>
                <label for="login-email" class="mb-1.5 block text-sm font-semibold">Email</label>
                <input
                    id="login-email"
                    name="email"
                    type="email"
                    value="{{ old('email') }}"
                    autocomplete="username"
                    inputmode="email"
                    maxlength="255"
                    required
                    autofocus
                    @if ($errors->has('email')) aria-invalid="true" aria-describedby="login-email-error" @else aria-invalid="false" @endif
                    class="min-h-12 w-full rounded-adara-control border border-adara-border bg-adara-surface px-4 py-3 text-sm text-adara-ink placeholder:text-adara-readable-muted focus:border-adara-primary focus:outline-none focus:ring-2 focus:ring-adara-primary/20"
                >
                @error('email')
                    <p id="login-email-error" role="alert" class="mt-1.5 text-sm font-medium text-adara-danger">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="login-password" class="mb-1.5 block text-sm font-semibold">Contraseña</label>
                <input
                    id="login-password"
                    name="password"
                    type="password"
                    autocomplete="current-password"
                    maxlength="255"
                    required
                    @if ($errors->has('password')) aria-invalid="true" aria-describedby="login-password-error" @else aria-invalid="false" @endif
                    class="min-h-12 w-full rounded-adara-control border border-adara-border bg-adara-surface px-4 py-3 text-sm text-adara-ink placeholder:text-adara-readable-muted focus:border-adara-primary focus:outline-none focus:ring-2 focus:ring-adara-primary/20"
                >
                @error('password')
                    <p id="login-password-error" role="alert" class="mt-1.5 text-sm font-medium text-adara-danger">{{ $message }}</p>
                @enderror
            </div>

            <button
                type="submit"
                data-auth-submit data-auth-loading-label="Ingresando…"
                class="mt-1 inline-flex min-h-12 items-center justify-center gap-2 rounded-adara-control bg-adara-primary px-5 py-3 text-sm font-semibold text-white transition-colors duration-150 hover:bg-adara-primary-hover focus-visible:outline-adara-primary disabled:cursor-wait disabled:opacity-75"
            >
                <span data-auth-spinner class="hidden h-4 w-4 animate-spin rounded-full border-2 border-white/40 border-t-white motion-reduce:animate-none" aria-hidden="true"></span>
                <span data-auth-label aria-live="polite">Iniciar sesión</span>
            </button>
        </form>

        <p class="mt-6 text-center text-sm text-adara-readable-muted">¿Todavía no tenés cuenta? <a href="{{ route('register', absolute: false) }}" class="font-semibold text-adara-primary underline-offset-4 hover:underline focus-visible:outline-adara-primary">Crear cuenta</a></p>
    </section>
@endsection

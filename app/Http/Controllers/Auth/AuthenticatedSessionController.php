<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class AuthenticatedSessionController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
        ], [
            'email.required' => 'Ingresá tu email.',
            'email.email' => 'Ingresá un email válido.',
            'email.max' => 'El email no puede superar los 255 caracteres.',
            'password.required' => 'Ingresá tu contraseña.',
            'password.max' => 'La contraseña no puede superar los 255 caracteres.',
        ]);

        $email = Str::lower(trim($credentials['email']));
        $rateLimitKey = $this->rateLimitKey($request, $email);

        if (RateLimiter::tooManyAttempts($rateLimitKey, 5)) {
            return $this->relativeRedirect($request, route('login', absolute: false))
                ->withErrors(['email' => 'No se pudo iniciar sesión. Esperá un minuto e intentá nuevamente.'])
                ->withInput($request->only('email'));
        }

        if (! Auth::attempt(['email' => $email, 'password' => $credentials['password'], 'is_active' => true])) {
            RateLimiter::hit($rateLimitKey, 60);

            return $this->relativeRedirect($request, route('login', absolute: false))
                ->withErrors(['email' => 'El email o la contraseña no son correctos.'])
                ->withInput($request->only('email'));
        }

        RateLimiter::clear($rateLimitKey);
        $request->session()->regenerate();

        return $this->relativeRedirect($request, $this->intendedDestination($request));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return $this->relativeRedirect($request, route('login', absolute: false))
            ->with('status', 'Cerraste sesión correctamente.');
    }

    private function relativeRedirect(Request $request, string $destination): RedirectResponse
    {
        $session = $request->session();

        if (! $session instanceof Store) {
            throw new \LogicException('La sesión web es necesaria para redirigir después de iniciar sesión.');
        }

        return (new RedirectResponse($destination))
            ->setRequest($request)
            ->setSession($session);
    }

    private function rateLimitKey(Request $request, string $email): string
    {
        return 'login-attempts:'.hash('sha256', $email.'|'.$request->ip());
    }

    private function intendedDestination(Request $request): string
    {
        $intendedUrl = $request->session()->pull('url.intended');

        if (! is_string($intendedUrl) || trim($intendedUrl) === '') {
            return route('home', absolute: false);
        }

        $parts = parse_url($intendedUrl);

        if ($parts === false) {
            return route('home', absolute: false);
        }

        $path = $parts['path'] ?? '/';

        if (! str_starts_with($path, '/') || str_starts_with($path, '//')) {
            return route('home', absolute: false);
        }

        try {
            $route = app('router')->getRoutes()->match(Request::create($path, 'GET'));
        } catch (MethodNotAllowedHttpException|NotFoundHttpException) {
            return route('home', absolute: false);
        }

        if (in_array($route->getName(), [
            'login',
            'register',
            'logout',
            'livewire.update',
            'livewire.upload-file',
            'livewire.preview-file',
        ], true)) {
            return route('home', absolute: false);
        }

        $query = isset($parts['query']) && $parts['query'] !== '' ? '?'.$parts['query'] : '';

        return $path.$query;
    }
}

<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected bool $authenticateByDefault = false;

    public function test_login_page_is_public_and_contains_csrf_protection(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Iniciar sesión')
            ->assertSee('name="_token"', false)
            ->assertDontSee('Registrarme');
    }

    public function test_health_check_remains_public(): void
    {
        $this->get('/up')->assertOk();
    }

    public function test_business_routes_redirect_guests_to_login(): void
    {
        foreach ([
            '/', '/productos', '/productos/1', '/stock', '/pedidos', '/pedidos/1',
            '/recepciones', '/ventas/nueva', '/ventas', '/cobros', '/clientes',
            '/clientes/deudas', '/proveedores', '/categorias', '/reportes',
            '/configuracion', '/respaldos', '/ayuda', '/dev/componentes',
        ] as $path) {
            $this->get($path)->assertRedirect(route('login'));
        }
    }

    public function test_valid_login_regenerates_session_and_returns_to_intended_page(): void
    {
        $user = User::factory()->create(['email' => 'alejo@example.test', 'password' => 'Correcta!1234']);

        $this->get('/productos')->assertRedirect(route('login'));
        $sessionIdBeforeLogin = $this->app['session']->driver()->getId();

        $this->post(route('login.store'), [
            'email' => ' ALEJO@example.test ',
            'password' => 'Correcta!1234',
        ])->assertRedirect('/productos');

        $this->assertAuthenticatedAs($user);
        $this->assertNotSame($sessionIdBeforeLogin, $this->app['session']->driver()->getId());
    }

    public function test_external_intended_url_is_not_used_after_login(): void
    {
        User::factory()->create(['password' => 'Correcta!1234']);

        $this->withSession(['url.intended' => 'https://evil.example/productos?filtro=crema'])
            ->post(route('login.store'), [
                'email' => User::query()->value('email'),
                'password' => 'Correcta!1234',
            ])
            ->assertRedirect('/productos?filtro=crema');
    }

    public function test_livewire_endpoint_is_not_used_as_login_destination(): void
    {
        User::factory()->create(['password' => 'Correcta!1234']);
        $updateRoute = collect(app('router')->getRoutes()->getRoutes())
            ->first(fn ($route) => $route->getName() === 'livewire.update');

        $this->withSession(['url.intended' => 'https://adara.test/'.$updateRoute->uri()])
            ->post(route('login.store'), [
                'email' => User::query()->value('email'),
                'password' => 'Correcta!1234',
            ])
            ->assertRedirect('/');
    }

    public function test_invalid_credentials_return_a_generic_error(): void
    {
        User::factory()->create();

        $this->from(route('login'))->post(route('login.store'), [
            'email' => User::query()->value('email'),
            'password' => 'incorrecta',
        ])->assertRedirect(route('login'))
            ->assertSessionHasErrors(['email' => 'El email o la contraseña no son correctos.'])
            ->assertSessionMissing('password');

        $this->assertGuest();
    }

    public function test_login_is_rate_limited_after_five_failed_attempts(): void
    {
        User::factory()->create(['email' => 'alejo@example.test']);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post(route('login.store'), [
                'email' => 'alejo@example.test',
                'password' => 'incorrecta',
            ])->assertSessionHasErrors('email');
        }

        $this->from(route('login'))->post(route('login.store'), [
            'email' => 'alejo@example.test',
            'password' => 'incorrecta',
        ])->assertSessionHasErrors('email', 'No se pudo iniciar sesión. Esperá un minuto e intentá nuevamente.');
    }

    public function test_authenticated_user_visiting_login_is_redirected_home(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('login'))
            ->assertRedirect(route('home'));
    }

    public function test_logout_invalidates_session_and_rotates_csrf_token(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->withSession(['marker' => 'keep-out']);
        $session = $this->app['session']->driver();
        $session->start();
        $tokenBeforeLogout = $session->token();

        $this->post(route('logout'))->assertRedirect('/login');

        $this->assertGuest();
        $this->assertNotSame($tokenBeforeLogout, $this->app['session']->driver()->token());
        $this->assertFalse($this->app['session']->driver()->has('marker'));
    }

    public function test_logout_route_uses_web_csrf_middleware(): void
    {
        $route = app('router')->getRoutes()->getByName('logout');
        $loginRoute = app('router')->getRoutes()->getByName('login.store');

        $this->assertContains('web', $route->middleware());
        $this->assertContains('auth', $route->middleware());
        $this->assertContains('web', $loginRoute->middleware());
        $this->assertContains(
            PreventRequestForgery::class,
            app('router')->getMiddlewareGroups()['web'],
        );
    }

    public function test_livewire_update_upload_and_preview_endpoints_are_private(): void
    {
        $router = app('router');
        $updateRoute = collect($router->getRoutes()->getRoutes())->first(fn ($route) => $route->getName() === 'livewire.update');
        $uploadRoute = $router->getRoutes()->getByName('livewire.upload-file');
        $previewRoute = $router->getRoutes()->getByName('livewire.preview-file');

        $this->post('/'.$updateRoute->uri(), [])->assertRedirect(route('login'));
        $this->post('/'.$uploadRoute->uri(), [])->assertRedirect(route('login'));
        $this->get('/'.str_replace('{filename}', 'test', $previewRoute->uri()))->assertRedirect(route('login'));
        $this->get('/storage/archivo-privado')->assertRedirect(route('login'));
        $this->put('/storage/archivo-privado', [])->assertRedirect(route('login'));
    }
}

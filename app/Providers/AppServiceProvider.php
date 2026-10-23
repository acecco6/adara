<?php

namespace App\Providers;

use App\Http\Middleware\EnsureActiveUser;
use App\Http\Middleware\EnsurePublicCatalogEnabled;
use Carbon\CarbonImmutable;
use Illuminate\Routing\Events\RouteMatched;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        config(['livewire.temporary_file_upload.middleware' => ['throttle:60,1']]);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        Livewire::setUpdateRoute(function ($handle, string $path) {
            return Route::post($path, $handle);
        });

        Livewire::addPersistentMiddleware([EnsureActiveUser::class, EnsurePublicCatalogEnabled::class]);

        Event::listen(RouteMatched::class, function (RouteMatched $event): void {
            if (in_array($event->route->getName(), [
                'livewire.upload-file',
                'livewire.preview-file',
                'storage.local',
                'storage.local.upload',
                'boost.browser-logs',
            ], true)) {
                $event->route->middleware(['auth', EnsureActiveUser::class]);
            }
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}

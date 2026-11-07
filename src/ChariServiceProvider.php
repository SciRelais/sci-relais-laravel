<?php

declare(strict_types=1);

namespace ChariRelais\Laravel;

use ChariRelais\Laravel\Console\CheckCommand;
use ChariRelais\Laravel\Http\VerifyChariSignature;
use ChariRelais\Laravel\Http\WebhookController;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class ChariServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/chari.php', 'chari');

        $this->app->singleton(ChariClient::class, fn ($app): ChariClient => new ChariClient($app->make(Factory::class), (array) $app['config']['chari']));
    }

    public function boot(): void
    {
        $path = config('chari.webhook_path');
        if (filled($path)) {
            // Hors du groupe « web » : pas de contrôle CSRF, c'est la signature qui protège cette adresse.
            Route::post((string) $path, WebhookController::class)->middleware(VerifyChariSignature::class)->name('chari.webhook');
        }

        if ($this->app->runningInConsole()) {
            $this->publishes([__DIR__.'/../config/chari.php' => config_path('chari.php')], 'chari-config');
            $this->commands([CheckCommand::class]);
        }
    }
}

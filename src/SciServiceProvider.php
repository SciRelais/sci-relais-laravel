<?php

declare(strict_types=1);

namespace SciRelais\Laravel;

use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use SciRelais\Laravel\Console\CheckCommand;
use SciRelais\Laravel\Http\VerifySciSignature;
use SciRelais\Laravel\Http\WebhookController;

class SciServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/sci.php', 'sci');

        $this->app->singleton(SciClient::class, fn ($app): SciClient => new SciClient($app->make(Factory::class), (array) $app['config']['sci']));
        $this->app->singleton(WebhookSecrets::class);
    }

    public function boot(): void
    {
        $path = config('sci.webhook_path');
        if (filled($path)) {
            // Hors du groupe « web » : pas de contrôle CSRF, c'est la signature qui protège cette adresse.
            Route::post((string) $path, WebhookController::class)->middleware(VerifySciSignature::class)->name('sci.webhook');
            // Un compte par vendeur : son adresse porte son identifiant, le secret vient de Sci::webhookSecretsUsing().
            Route::post(rtrim((string) $path, '/').'/{account}', WebhookController::class)->where('account', '[A-Za-z0-9_-]{1,64}')
                ->middleware(VerifySciSignature::class)->name('sci.webhook.account');
        }

        if ($this->app->runningInConsole()) {
            $this->publishes([__DIR__.'/../config/sci.php' => config_path('sci.php')], 'sci-config');
            $this->commands([CheckCommand::class]);
        }
    }
}

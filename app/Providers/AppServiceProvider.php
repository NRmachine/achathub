<?php

namespace App\Providers;

use App\Services\StorefrontNavigation;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use SchaeferSoft\LaravelLlmsTxt\LlmsTxt;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(StorefrontNavigation::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        View::composer('layouts.app', function ($view): void {
            $view->with(app(StorefrontNavigation::class)->data());
        });

        if ($this->app->isProduction()) {
            // The Vercel container image can contain the local SQLite database.
            // Production must always use the injected Neon PostgreSQL URL.
            config()->set('database.default', 'pgsql');

            URL::forceScheme('https');

            // HTTP requests must keep the public host forwarded by Vercel. Forcing
            // APP_URL here would make every asset and redirect use a stale build
            // value when the container runtime receives its environment late.
            if ($this->app->runningInConsole()) {
                URL::forceRootUrl(config('app.url'));
            }
        }

        // Keep generated pagination links relative to the current public host.
        Paginator::currentPathResolver(fn () => request()->getPathInfo());
        Paginator::useBootstrapFive();
        LlmsTxt::configure(fn ($llms) => $llms
            ->title('AchatHub')
            ->description('Boutique en ligne généraliste et solution de revente professionnelle.')
            ->section('Boutique', fn ($section) => $section
                ->entry('Catalogue', url('/'), 'Produits, catégories et recherche')
                ->entry('Devenir revendeur', url('/devenir-revendeur'), 'Présentoirs, dépôt-vente et achat en gros')
                ->entry('Support', url('/support'), 'Service client AchatHub'))
        );
    }
}

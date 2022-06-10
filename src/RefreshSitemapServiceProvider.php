<?php

namespace Kwaadpepper\RefreshSitemap;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;
use Kwaadpepper\RefreshSitemap\Console\Commands\RefreshSitemap;
use Kwaadpepper\RefreshSitemap\Jobs\GenerateSitemap;

class RefreshSitemapServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config' => \config_path(),
        ], 'config');

        if (\config('refresh-sitemap.schedule', false)) {
            $this->app->booted(function () {
                /** @var \Illuminate\Console\Scheduling\Schedule $schedule */
                $schedule = app(Schedule::class);
                $schedule->job(GenerateSitemap::class)
                    ->cron(\config('refresh-sitemap.cron', '45 15 * * *'));
            });
        }
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            \sprintf('%s/../config/refresh-sitemap.php', __DIR__),
            'refresh-sitemap'
        );
        $this->commands([
            RefreshSitemap::class
        ]);
    }
}

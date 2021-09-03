<?php

namespace Kwaadpepper\RefreshSitemap;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;
use Kwaadpepper\RefreshSitemap\Jobs\GenerateSitemap;

class RefreshSitemapServiceProvider extends ServiceProvider
{

    protected $commands = [
        'Kwaadpepper\RefreshSitemap\Console\Commands\RefreshSitemap'
    ];

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
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

    public function register()
    {
        $this->mergeConfigFrom(
            \sprintf('%s/../config/refresh-sitemap.php', __DIR__),
            'refresh-sitemap'
        );
        $this->commands($this->commands);
    }
}

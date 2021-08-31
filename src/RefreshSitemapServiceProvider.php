<?php

namespace Kwaadpepper\RefreshSitemap;

use Illuminate\Support\ServiceProvider;

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
            __DIR__ . '/../config' => config_path(),
        ], 'config');
    }

    public function register()
    {
        $this->mergeConfigFrom(
            sprintf('%s/../config/refresh-sitemap.php', __DIR__),
            'refresh-sitemap'
        );
        $this->commands($this->commands);
    }
}

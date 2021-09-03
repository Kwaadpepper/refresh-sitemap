<?php

namespace Kwaadpepper\RefreshSitemap\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Kwaadpepper\RefreshSitemap\Exceptions\SitemapException;
use Kwaadpepper\RefreshSitemap\Lib\SitemapGenerator;

class GenerateSitemap implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function handle()
    {
        $sitemapGenerator = new SitemapGenerator();
        Log::debug('Generating Sitemap..');
        try {
            $dest = \public_path('sitemap.xml');
            if (File::exists($dest)) {
                Log::debug('public/sitemap.xml exist, will overwrite.');
            }
            $sitemapGenerator->writeToFile(\public_path('sitemap.xml'));
        } catch (SitemapException $e) {
            Log::error('An error occured while generating sitemap');
            Log::error($e->getMessage());
            Log::debug($e);
        }
        Log::debug('A new sitemap.xml was generated');
    }
}

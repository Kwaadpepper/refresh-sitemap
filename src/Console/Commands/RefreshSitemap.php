<?php

namespace Kwaadpepper\RefreshSitemap\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Kwaadpepper\RefreshSitemap\Exceptions\SitemapException;
use Kwaadpepper\RefreshSitemap\Lib\SitemapGenerator;

class RefreshSitemap extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sitemap:refresh {--D|dry-run}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Refresh the sitemap.xml in public';

    /**
     * Execute the console command.
     *
     * @return integer
     */
    public function handle(): int
    {
        $sitemapGenerator = new SitemapGenerator();

        $this->info('Generating Sitemap..');

        try {
            if ($this->option('dry-run')) {
                $this->info($sitemapGenerator->generateSiteMap());
                return 1;
            }

            $dest = \public_path('sitemap.xml');

            if (File::exists($dest)) {
                $this->info('public/sitemap.xml exist, will overwrite.');
            }

            $sitemapGenerator->writeToFile(\public_path('sitemap.xml'));
        } catch (SitemapException $e) {
            $this->error('An error occured while generating sitemap');
            $this->error($e->getMessage());
            \report($e);
            if (config('app.debug')) {
                dump($e);
            }
        }//end try

        $this->info('A new sitemap.xml was generated');

        return 0;
    }
}

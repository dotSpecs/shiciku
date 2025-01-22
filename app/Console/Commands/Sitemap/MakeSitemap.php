<?php

namespace App\Console\Commands\Sitemap;

use Illuminate\Console\Command;
use Spatie\Sitemap\SitemapIndex;

class MakeSitemap extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sitemap:make';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '生成地图';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        SitemapIndex::create()
            ->add('/sitemap/sitemap_basic.xml')
            ->add('/sitemap/sitemap_authors.xml')
            ->add('/sitemap/sitemap_books.xml')
            ->add('/sitemap/sitemap_poems_1.xml')
            ->add('/sitemap/sitemap_poems_2.xml')
            ->add('/sitemap/sitemap_poems_3.xml')
            ->add('/sitemap/sitemap_poems_4.xml')
            ->add('/sitemap/sitemap_poems_5.xml')
            ->add('/sitemap/sitemap_poems_6.xml')
            ->add('/sitemap/sitemap_poems_7.xml')
            ->add('/sitemap/sitemap_poems_8.xml')
            ->add('/sitemap/sitemap_poems_9.xml')
            ->add('/sitemap/sitemap_poems_10.xml')
            ->add('/sitemap/sitemap_poems_11.xml')
            ->add('/sitemap/sitemap_poems_12.xml')
            ->add('/sitemap/sitemap_poems_13.xml')
            ->add('/sitemap/sitemap_poems_14.xml')
            ->add('/sitemap/sitemap_poems_15.xml')
            ->add('/sitemap/sitemap_poems_16.xml')
            ->add('/sitemap/sitemap_poems_17.xml')
            ->add('/sitemap/sitemap_poems_18.xml')
            ->add('/sitemap/sitemap_poems_19.xml')
            ->add('/sitemap/sitemap_poems_20.xml')
            ->add('/sitemap/sitemap_poems_21.xml')
            ->writeToFile(public_path('sitemap/sitemap.xml'));
    }
}

<?php

namespace App\Console\Commands\Sitemap;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\Tags\Url;

class MakeBasicSitemap extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sitemap:make-basic';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '生成基础地图';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        Sitemap::create()
            ->add(
                Url::create('/')->setLastModificationDate(Carbon::yesterday())
                    ->setChangeFrequency(Url::CHANGE_FREQUENCY_DAILY)
            )
            ->add(
                Url::create('/poem')->setLastModificationDate(Carbon::yesterday())
                    ->setChangeFrequency(Url::CHANGE_FREQUENCY_WEEKLY)
            )
            ->add(
                Url::create('/author')->setLastModificationDate(Carbon::yesterday())
                    ->setChangeFrequency(Url::CHANGE_FREQUENCY_WEEKLY)
            )
            ->add(
                Url::create('/book')->setLastModificationDate(Carbon::yesterday())
                    ->setChangeFrequency(Url::CHANGE_FREQUENCY_WEEKLY)
            )
            ->writeToFile(public_path('sitemap/sitemap_basic.xml'));

        return 0;
    }
}

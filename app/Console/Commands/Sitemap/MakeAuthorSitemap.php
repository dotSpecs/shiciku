<?php

namespace App\Console\Commands\Sitemap;

use App\Models\Author;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\Tags\Url;

class MakeAuthorSitemap extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sitemap:make-author';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '生成作者地图';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        ini_set('memory_limit', '1024M');
        $path = public_path('sitemap/sitemap_authors.xml');

        $sitemap = Sitemap::create();

        Author::query()
            ->select('id','author_id')
            ->orderBy('id')
            // ->where('id', '<=', 10100)
            ->chunk(50, function ($authors) use ($sitemap, $path) {
                foreach ($authors as $author) {
                    $sitemap->add(
                        Url::create('/author/' . $author->author_id)
                            ->setLastModificationDate(Carbon::yesterday())
                            ->setChangeFrequency(Url::CHANGE_FREQUENCY_YEARLY)
                    );
                    $this->info($author->id . ' prcessed');
                }
            });
        $sitemap->writeToFile($path);
    }
}

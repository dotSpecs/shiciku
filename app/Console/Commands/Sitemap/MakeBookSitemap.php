<?php

namespace App\Console\Commands\Sitemap;

use App\Models\BookArticle;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\Tags\Url;

class MakeBookSitemap extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sitemap:make-book';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '生成书籍地图';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        ini_set('memory_limit', '1024M');
        $path = public_path('sitemap/sitemap_books.xml');

        $sitemap = Sitemap::create();

        BookArticle::query()
            ->select('id','article_id', 'book_id')
            ->with('book')
            ->orderBy('id')
            // ->where('id', '<=', 10100)
            ->chunk(50, function ($articles) use ($sitemap, $path) {
                foreach ($articles as $article) {
                    $sitemap->add(
                        Url::create('/book/' . $article->book->book_id . '/' . $article->article_id)
                            ->setLastModificationDate(Carbon::yesterday())
                            ->setChangeFrequency(Url::CHANGE_FREQUENCY_YEARLY)
                    );
                    $this->info($article->id . ' prcessed');
                }
            });
        $sitemap->writeToFile($path);
    }
}

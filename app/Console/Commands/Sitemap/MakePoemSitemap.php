<?php

namespace App\Console\Commands\Sitemap;

use App\Models\Poem;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\Tags\Url;

class MakePoemSitemap extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sitemap:make-poem';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '生成诗词地图';

    private const CHUNK_SIZE = 50000;

    /**
     * Execute the console command.
     */
    public function handle()
    {
        ini_set('memory_limit', '1024M');

        $maxId = Poem::max('id');
        $totalFiles = ceil($maxId / self::CHUNK_SIZE);

        for ($i = 0; $i < $totalFiles; $i++) {
            $min = ($i * self::CHUNK_SIZE) + 1;
            $max = ($i + 1) * self::CHUNK_SIZE;

            $path = public_path('sitemap/sitemap_poems_' . ($i + 1) . '.xml');
            $sitemap = Sitemap::create();

            Poem::query()
                ->select('id', 'poem_id')
                ->orderBy('id')
                ->where('id', '<=', $max)
                ->where('id', '>=', $min)
                ->chunk(500, function ($poems) use ($sitemap, $path) {
                    foreach ($poems as $poem) {
                        $sitemap->add(
                            Url::create('/poem/' . $poem->poem_id)
                                ->setLastModificationDate(Carbon::yesterday())
                                ->setChangeFrequency(Url::CHANGE_FREQUENCY_YEARLY)
                        );
                        $this->info($poem->id . ' processed');
                    }
                });

            $sitemap->writeToFile($path);
            $this->info("Generated sitemap for IDs {$min}-{$max}");
        }

        return 0;
    }
}

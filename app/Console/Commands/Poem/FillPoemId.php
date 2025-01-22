<?php

namespace App\Console\Commands\Poem;

use App\Models\BookArticle;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class FillPoemId extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fill:poem:id';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        BookArticle::chunk(100, function ($articles) {
            foreach ($articles as $article) {
                $article->article_id = strtolower(Str::random(10));
                $article->save();
                $this->info('Article ID filled: ' . $article->id);
            }
        });
    }
}

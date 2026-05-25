<?php

namespace App\Providers;

use App\Models\Book;
use App\Models\Mingju;
use App\Models\Poem;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Relation::enforceMorphMap([
            'poem' => Poem::class,
            'mingju' => Mingju::class,
            'book' => Book::class,
        ]);
    }
}

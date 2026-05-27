<?php

use App\Http\Controllers\Api\AuthorController;
use App\Http\Controllers\Api\BookController;
use App\Http\Controllers\Api\FavoriteController;
use App\Http\Controllers\Api\HomeController;
use App\Http\Controllers\Api\MingjuController;
use App\Http\Controllers\Api\PoemController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\Api\ToolController;
use App\Http\Controllers\Api\WxAuthController;
use App\Http\Controllers\Api\ZhuantiController;
use Illuminate\Support\Facades\Route;

Route::post('/wx/login', [WxAuthController::class, 'login']);

Route::middleware('wx.sign')->group(function () {
    Route::get('/wx/me', [WxAuthController::class, 'me']);
    Route::put('/wx/me', [WxAuthController::class, 'updateMe']);

    Route::get('/upload/token', [ToolController::class, 'uploadToken']);
    Route::post('/audio', [ToolController::class, 'audio']);

    Route::get('/home', [HomeController::class, 'index']);
    Route::get('/poems', [PoemController::class, 'index']);
    Route::get('/poems/{poem_id}', [PoemController::class, 'show']);
    Route::get('/poems/{poem_id}/yinyi', [PoemController::class, 'yinYizhu']);
    Route::get('/zhuantis/{alias}', [ZhuantiController::class, 'show']);
    Route::get('/books', [BookController::class, 'index']);
    Route::get('/books/{book_id}', [BookController::class, 'show']);
    Route::get('/articles/{article_id}', [BookController::class, 'article']);
    Route::get('/mingjus', [MingjuController::class, 'index']);
    Route::get('/mingjus/{mingju_id}', [MingjuController::class, 'show']);
    Route::get('/authors', [AuthorController::class, 'index']);
    Route::get('/authors/{author_id}', [AuthorController::class, 'show']);

    Route::get('/favorites', [FavoriteController::class, 'index']);
    Route::get('/favorites/{type}/{id}', [FavoriteController::class, 'status'])
        ->whereIn('type', ['poem', 'mingju', 'book', 'book_article']);
    Route::post('/favorites/{type}/{id}', [FavoriteController::class, 'store'])
        ->whereIn('type', ['poem', 'mingju', 'book', 'book_article']);
    Route::delete('/favorites/{type}/{id}', [FavoriteController::class, 'destroy'])
        ->whereIn('type', ['poem', 'mingju', 'book', 'book_article']);

    Route::get('/search', [SearchController::class, 'index']);
});

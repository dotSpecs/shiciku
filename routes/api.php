<?php

use App\Http\Controllers\Api\AuthorController;
use App\Http\Controllers\Api\BookController;
use App\Http\Controllers\Api\MingjuController;
use App\Http\Controllers\Api\PoemController;
use App\Http\Controllers\Api\WxAuthController;
use App\Http\Controllers\Api\ZhuantiController;
use Illuminate\Support\Facades\Route;

Route::post('/wx/login', [WxAuthController::class, 'login']);

Route::middleware('wx.sign')->group(function () {
    Route::get('/wx/me', [WxAuthController::class, 'me']);

    Route::get('/poems', [PoemController::class, 'index']);
    Route::get('/poems/{poem_id}', [PoemController::class, 'show']);
    Route::get('/zhuantis/{alias}', [ZhuantiController::class, 'show']);
    Route::get('/books', [BookController::class, 'index']);
    Route::get('/books/{book_id}', [BookController::class, 'show']);
    Route::get('/articles/{article_id}', [BookController::class, 'article']);
    Route::get('/mingjus', [MingjuController::class, 'index']);
    Route::get('/mingjus/{mingju_id}', [MingjuController::class, 'show']);
    Route::get('/authors/{author_id}', [AuthorController::class, 'show']);
});

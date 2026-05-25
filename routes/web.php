<?php

use App\Http\Controllers\Web\AuthorController;
use App\Http\Controllers\Web\BookController;
use App\Http\Controllers\Web\IndexController;
use App\Http\Controllers\Web\MingjuController;
use App\Http\Controllers\Web\PoemController;

use Illuminate\Support\Facades\Route;

Route::get('/', [IndexController::class, 'index'])->name('index');

Route::get('/poem', [PoemController::class, 'index'])->name('poem.index');
Route::get('/poem/search', [PoemController::class, 'search'])->name('search');
Route::get('/poem/{alias}', [PoemController::class, 'zhuanti'])
    ->where('alias', '[a-z]+')
    ->name('poem.zhuanti');
Route::get('/poem/{slug}', [PoemController::class, 'show'])->name('poem.show');
Route::post('/poem/{poem_id}/audio', [PoemController::class, 'audio'])->name('poem.audio');

Route::get('/author', [AuthorController::class, 'index'])->name('author.index');
Route::get('/author/{author_id}', [AuthorController::class, 'show'])->name('author.show');

Route::get('/mingju', [MingjuController::class, 'index'])->name('mingju.index');
Route::get('/mingju/{mingju_id}', [MingjuController::class, 'show'])->name('mingju.show');

Route::get('/book', [BookController::class, 'index'])->name('book.index');
Route::get('/book/{book_id}', [BookController::class, 'show'])->name('book.show');
Route::get('/book/{book_id}/{article_id}', [BookController::class, 'article'])->name('book.article');
Route::post('/book/{book_id}/{article_id}/audio', [BookController::class, 'audio'])->name('book.audio');

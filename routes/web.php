<?php

use App\Http\Controllers\Web\AuthorController;
use App\Http\Controllers\Web\BookController;
use App\Http\Controllers\Web\IndexController;
use App\Http\Controllers\Web\PoemController;
use App\Http\Controllers\Web\QuoteController;

use Illuminate\Support\Facades\Route;

Route::get('/', [IndexController::class, 'index'])->name('index');

Route::get('/poem', [PoemController::class, 'index'])->name('poem.index');
Route::get('/poem/search', [PoemController::class, 'search'])->name('search');
Route::get('/poem/{poem_id}', [PoemController::class, 'show'])->name('poem.show');

Route::get('/author', [AuthorController::class, 'index'])->name('author.index');
Route::get('/author/{author_id}', [AuthorController::class, 'show'])->name('author.show');

// Route::get('/quote', [QuoteController::class, 'index'])->name('quote.index');
// Route::get('/quote/{quote_id}', [QuoteController::class, 'show'])->name('quote.show');

Route::get('/book', [BookController::class, 'index'])->name('book.index');
Route::get('/book/{book_id}', [BookController::class, 'show'])->name('book.show');
Route::get('/book/{book_id}/{article_id}', [BookController::class, 'article'])->name('book.article');

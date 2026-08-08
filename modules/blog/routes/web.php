<?php

use Illuminate\Support\Facades\Route;
use Modules\Blog\Http\Controllers\BlogController;

Route::get('/', [BlogController::class, 'index'])->name('index');
Route::get('/assets/blog.css', [BlogController::class, 'styles'])->name('styles');
Route::get('/assets/templates/{slug}.js', [BlogController::class, 'templateScript'])->name('template-script');
Route::get('/category', [BlogController::class, 'categories'])->name('categories');
Route::get('/category/{slug}', [BlogController::class, 'category'])->name('category');
Route::get('/tag/{slug}', [BlogController::class, 'tag'])->name('tag');
Route::get('/search', [BlogController::class, 'search'])->name('search');
Route::get('/{slug}', [BlogController::class, 'show'])->name('show');

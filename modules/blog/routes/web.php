<?php

use Illuminate\Support\Facades\Route;
use Modules\Blog\Http\Controllers\BlogController;
use Modules\Blog\Http\Controllers\Frontend\WriterController;

Route::get('/', [BlogController::class, 'index'])->name('index');
Route::get('/assets/blog.css', [BlogController::class, 'styles'])->name('styles');
Route::get('/assets/templates/{slug}.js', [BlogController::class, 'templateScript'])->name('template-script');
Route::get('/category', [BlogController::class, 'categories'])->name('categories');
Route::get('/category/{slug}', [BlogController::class, 'category'])->name('category');
Route::get('/tag/{slug}', [BlogController::class, 'tag'])->name('tag');
Route::get('/search', [BlogController::class, 'search'])->name('search');
Route::middleware(['auth', 'verified'])->prefix('writer')->name('writer.')->group(function (): void {
    Route::get('/', [WriterController::class, 'index'])->name('index');
    Route::get('/posts/create', [WriterController::class, 'create'])->name('posts.create');
    Route::post('/posts', [WriterController::class, 'store'])->name('posts.store');
    Route::post('/posts/autosave', [WriterController::class, 'autosave'])->name('posts.autosave');
    Route::post('/posts/slug', [WriterController::class, 'slug'])->name('posts.slug');
    Route::get('/posts/{post}/edit', [WriterController::class, 'edit'])->name('posts.edit');
    Route::put('/posts/{post}', [WriterController::class, 'update'])->name('posts.update');
    Route::get('/posts/{post}/preview', [WriterController::class, 'preview'])->name('posts.preview');
    Route::delete('/posts/{post}', [WriterController::class, 'destroy'])->name('posts.destroy');
    Route::get('/posts/trash', [WriterController::class, 'trash'])->name('posts.trash');
    Route::post('/posts/{post}/restore', [WriterController::class, 'restore'])->name('posts.restore');
    Route::delete('/posts/{post}/force', [WriterController::class, 'forceDestroy'])->name('posts.force-destroy');
    Route::get('/media', [WriterController::class, 'mediaIndex'])->name('media.index');
    Route::post('/media', [WriterController::class, 'mediaStore'])->name('media.store');
});
Route::get('/{slug}', [BlogController::class, 'show'])->name('show');

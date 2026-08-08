<?php

use Illuminate\Support\Facades\Route;
use Modules\Blog\Http\Controllers\Admin\CategoryController;
use Modules\Blog\Http\Controllers\Admin\PostController;
use Modules\Blog\Http\Controllers\Admin\TagController;
use Modules\Blog\Http\Controllers\Admin\TemplateController;
use Modules\Blog\Http\Controllers\Admin\SettingsController;

Route::middleware('permission:blog.view')->group(function (): void {
    Route::get('/', [SettingsController::class, 'edit'])->name('settings.edit');
    Route::get('posts/{post}/preview', [PostController::class, 'preview'])->name('posts.preview');
    Route::get('posts', [PostController::class, 'index'])->name('posts.index');
    Route::get('categories', [CategoryController::class, 'index'])->name('categories.index');
});

Route::middleware('permission:blog.create')->group(function (): void {
    Route::post('posts/slug', [PostController::class, 'slug'])->name('posts.slug');
    Route::get('posts/create', [PostController::class, 'create'])->name('posts.create');
    Route::post('posts', [PostController::class, 'store'])->name('posts.store');
    Route::post('categories/quick', [CategoryController::class, 'quickStore'])->name('categories.quick-store');
});

Route::middleware('permission:blog.update')->group(function (): void {
    Route::post('posts/autosave', [PostController::class, 'autosave'])->name('posts.autosave');
    Route::get('posts/{post}/edit', [PostController::class, 'edit'])->name('posts.edit');
    Route::patch('posts/{post}/quick', [PostController::class, 'quickUpdate'])->name('posts.quick-update');
    Route::match(['put', 'patch'], 'posts/{post}', [PostController::class, 'update'])->name('posts.update');
});

Route::middleware('permission:blog.delete')->group(function (): void {
    Route::post('posts/bulk', [PostController::class, 'bulk'])->name('posts.bulk');
    Route::delete('posts/trash/empty', [PostController::class, 'emptyTrash'])->name('posts.trash.empty');
    Route::delete('posts/{post}', [PostController::class, 'destroy'])->name('posts.destroy');
    Route::delete('posts/{post}/revisions/{revision}', [PostController::class, 'destroyRevision'])->name('posts.revisions.destroy');
});

Route::post('posts/{post}/revisions/{revision}/restore', [PostController::class, 'restoreRevision'])
    ->middleware('permission:blog.revisions.restore')
    ->name('posts.revisions.restore');

Route::middleware('permission:blog.media.manage')->group(function (): void {
    Route::get('media', [PostController::class, 'mediaLibrary'])->name('media.index');
    Route::post('media', [PostController::class, 'uploadMedia'])->name('media.store');
    Route::patch('media/{media}', [PostController::class, 'updateMedia'])->name('media.update');
    Route::delete('media/{media}', [PostController::class, 'destroyMedia'])->name('media.destroy');
});

Route::middleware('permission:blog.categories.manage')->group(function (): void {
    Route::post('categories/bulk', [CategoryController::class, 'bulk'])->name('categories.bulk');
    Route::delete('categories/trash/empty', [CategoryController::class, 'emptyTrash'])->name('categories.trash.empty');
    Route::post('categories', [CategoryController::class, 'store'])->name('categories.store');
    Route::match(['put', 'patch'], 'categories/{category}', [CategoryController::class, 'update'])->name('categories.update');
    Route::delete('categories/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy');
});

Route::middleware('permission:blog.tags.manage')->group(function (): void {
    Route::get('tags', [TagController::class, 'index'])->name('tags.index');
    Route::post('tags', [TagController::class, 'store'])->name('tags.store');
    Route::match(['put', 'patch'], 'tags/{tag}', [TagController::class, 'update'])->name('tags.update');
    Route::delete('tags/{tag}', [TagController::class, 'destroy'])->name('tags.destroy');
});

Route::middleware('permission:blog.templates.manage')->group(function (): void {
    Route::patch('/', [SettingsController::class, 'update'])->name('settings.update');
    Route::get('templates/catalog', [TemplateController::class, 'catalog'])->name('templates.catalog');
    Route::get('templates', [TemplateController::class, 'index'])->name('templates.index');
    Route::post('templates', [TemplateController::class, 'store'])->name('templates.store');
    Route::get('templates/{template}/edit', [TemplateController::class, 'edit'])->name('templates.edit');
    Route::match(['put', 'patch'], 'templates/{template}', [TemplateController::class, 'update'])->name('templates.update');
    Route::delete('templates/{template}', [TemplateController::class, 'destroy'])->name('templates.destroy');
    Route::post('templates/{template}/duplicate', [TemplateController::class, 'duplicate'])->name('templates.duplicate');
});

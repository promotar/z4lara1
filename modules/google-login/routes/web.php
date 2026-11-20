<?php

use Illuminate\Support\Facades\Route;
use Modules\GoogleLogin\Http\Controllers\Auth\GoogleLoginController;

Route::get('/auth/google', [GoogleLoginController::class, 'redirect'])->name('auth.redirect');
Route::get('/auth/google/callback', [GoogleLoginController::class, 'callback'])->name('auth.callback');

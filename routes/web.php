<?php

use App\Http\Controllers\Gallery\GalleryClientAuthController;
use App\Http\Controllers\Gallery\GalleryController;
use App\Http\Controllers\Google\GoogleAuthController;
use App\Http\Controllers\Home\HomeController;
use Illuminate\Support\Facades\Route;

// ── Public home ───────────────────────────────────────────────────────────────
Route::get('/', [HomeController::class, 'index'])->name('home');
Route::redirect('/dashboard', '/admin')->name('dashboard');

// ── Authenticated photographer routes ─────────────────────────────────────────
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('admin/photos/{photo}/thumbnail', [GalleryController::class, 'adminThumbnail'])->name('gallery.photo.admin-thumbnail');
    // Google Drive OAuth
    Route::prefix('auth/google')->name('google.')->group(function () {
        Route::get('redirect', [GoogleAuthController::class, 'redirect'])->name('redirect');
        Route::post('disconnect', [GoogleAuthController::class, 'disconnect'])->name('disconnect');
    });
});

Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])
    ->name('google.callback');

// ── Public gallery routes ─────────────────────────────────────────────────────
Route::prefix('g')->name('gallery.')->group(function () {
    Route::get('{slug}', [GalleryController::class, 'show'])->name('show');
    Route::get('{slug}/download', [GalleryController::class, 'downloadArchive'])->name('download');
    Route::get('{slug}/login', [GalleryClientAuthController::class, 'show'])->name('login');
    Route::get(
        '{slug}/login/google',
        [GalleryClientAuthController::class, 'redirectToGoogle']
    )->name('login.google');
    Route::get('{slug}/photo/{photo}/download', [GalleryController::class, 'download'])->name('photo.download');
    Route::get('{slug}/photo/{photo}/preview', [GalleryController::class, 'preview'])->name('photo.preview');
    Route::get('{slug}/photo/{photo}/thumbnail', [GalleryController::class, 'thumbnail'])->name('photo.thumbnail');
});

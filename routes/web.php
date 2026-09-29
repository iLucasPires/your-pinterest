<?php

use App\Http\Controllers\Gallery\GalleryController;
use App\Http\Controllers\Google\GoogleAuthController;
use App\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Route;

// ── Public home ───────────────────────────────────────────────────────────────
Route::get('/', [HomeController::class, 'index'])->name('home');
Route::redirect('/dashboard', '/admin')->name('dashboard');

// ── Authenticated photographer routes ─────────────────────────────────────────
Route::middleware(['auth', 'verified'])->group(function () {
    // Google Drive OAuth
    Route::prefix('auth/google')->name('google.')->group(function () {
        Route::get('redirect',    [GoogleAuthController::class, 'redirect'])->name('redirect');
        Route::get('callback',    [GoogleAuthController::class, 'callback'])->name('callback');
        Route::post('disconnect', [GoogleAuthController::class, 'disconnect'])->name('disconnect');
    });
});

// ── Public gallery routes ─────────────────────────────────────────────────────
Route::prefix('g')->name('gallery.')->group(function () {
    Route::get('{slug}',                            [GalleryController::class, 'show'])->name('show');
    Route::post('{slug}/access',                    [GalleryController::class, 'storeAccess'])->name('access.store');
    Route::delete('{slug}/access',                  [GalleryController::class, 'destroyAccess'])->name('access.destroy');
    Route::get('{slug}/photo/{photo}/download',     [GalleryController::class, 'download'])->name('photo.download');
    Route::get('{slug}/photo/{photo}/preview',      [GalleryController::class, 'preview'])->name('photo.preview');
});


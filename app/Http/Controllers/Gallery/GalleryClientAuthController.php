<?php

namespace App\Http\Controllers\Gallery;

use App\Http\Controllers\Controller;
use App\Models\Gallery\Gallery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;

class GalleryClientAuthController extends Controller
{
    public function show(string $slug): RedirectResponse
    {
        $gallery = $this->privateGallery($slug);

        return redirect()->route('gallery.show', [
            'slug' => $gallery->slug,
            'mode' => 'login',
        ]);
    }

    public function redirectToGoogle(Request $request, string $slug): RedirectResponse
    {
        $gallery = $this->privateGallery($slug);
        $request->session()->put('gallery_google_login', [
            'gallery_id' => $gallery->id,
            'slug' => $gallery->slug,
        ]);

        return Socialite::driver('google')->redirect();
    }

    private function privateGallery(string $slug): Gallery
    {
        return Gallery::query()
            ->with('client')
            ->where('slug', $slug)
            ->where('is_published', true)
            ->where('access_type', Gallery::ACCESS_PRIVATE)
            ->firstOrFail();
    }
}

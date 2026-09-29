<?php

namespace App\Http\Controllers;

use App\Models\Gallery\Gallery;
use App\Models\Gallery\Photo;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function index(): View
    {
        $recentGalleries = Gallery::query()
            ->with('client')
            ->where('is_published', true)
            ->latest('published_at')
            ->take(6)
            ->get();

        return view('pages.home', [
            'recentGalleries' => $recentGalleries,
            'publicPhotos' => $this->randomPublicPhotos(),
        ]);
    }

    /** @return Collection<int, Photo> */
    private function randomPublicPhotos(): Collection
    {
        return Photo::query()
            ->select(['id', 'gallery_id', 'thumbnail_url', 'filename'])
            ->whereNotNull('thumbnail_url')
            ->whereHas('gallery', function ($query): void {
                $query
                    ->where('is_published', true)
                    ->where('access_type', Gallery::ACCESS_PUBLIC);
            })
            ->inRandomOrder()
            ->limit(24)
            ->get();
    }
}
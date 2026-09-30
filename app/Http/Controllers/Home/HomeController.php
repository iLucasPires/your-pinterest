<?php

namespace App\Http\Controllers\Home;

use App\Http\Controllers\Controller;
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
            ->select(['id', 'gallery_id', 'thumbnail_path', 'filename'])
            ->with('gallery:id,slug')
            ->whereNotNull('thumbnail_path')
            ->whereHas('gallery', function ($query): void {
                $query
                    ->where('is_published', true)
                    ->publiclyDiscoverable();
            })
            ->inRandomOrder()
            ->limit(24)
            ->get()
            ->each(function (Photo $photo): void {
                $photo->setAttribute(
                    'thumbnail_url',
                    $photo->displayThumbnailUrl()
                );
            });
    }
}

<?php

namespace App\Http\Controllers\Home;

use App\Http\Controllers\Controller;
use App\Models\Gallery\Gallery;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function index(): View
    {
        $recentGalleries = Gallery::query()
            ->with(['client', 'coverPhoto'])
            ->withCount('photos')
            ->where('is_published', true)
            ->publiclyDiscoverable()
            ->latest('published_at')
            ->limit(5)
            ->get();

        return view('pages.home', [
            'recentGalleries' => $recentGalleries,
        ]);
    }
}

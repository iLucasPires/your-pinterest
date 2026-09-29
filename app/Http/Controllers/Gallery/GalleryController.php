<?php

namespace App\Http\Controllers\Gallery;

use App\Http\Controllers\Controller;
use App\Models\Gallery\Gallery;
use App\Models\Gallery\GalleryAccess;
use App\Models\Gallery\Photo;
use App\Services\Google\GoogleDriveProviderFactory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GalleryController extends Controller
{
    public function __construct(
        private readonly GoogleDriveProviderFactory $factory,
    ) {}

    public function show(Request $request, string $slug): View|RedirectResponse
    {
        $gallery = Gallery::query()
            ->with('client')
            ->where('slug', $slug)
            ->where('is_published', true)
            ->firstOrFail();

        $hasAccess = ! $gallery->isProtected() || $this->hasAccess($request, $gallery);

        return view('pages.gallery', compact('gallery', 'hasAccess'));
    }

    public function storeAccess(Request $request, string $slug): RedirectResponse
    {
        $gallery = Gallery::where('slug', $slug)
            ->where('is_published', true)
            ->firstOrFail();

        if (! $gallery->isProtected()) {
            return redirect()->route('gallery.show', $slug);
        }

        $key = 'gallery-access:'.$slug.':'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, 10)) {
            $seconds = RateLimiter::availableIn($key);

            return back()->withErrors([
                'code' => "Too many attempts. Please wait {$seconds} seconds.",
            ]);
        }

        $request->validate([
            'code' => ['required', 'digits:6'],
        ]);

        if (! Hash::check($request->input('code'), $gallery->access_code_hash)) {
            RateLimiter::hit($key, 60);

            return back()->withErrors([
                'code' => 'Incorrect access code. Please try again.',
            ]);
        }

        RateLimiter::clear($key);

        $request->session()->put("gallery_access_{$gallery->id}", true);

        GalleryAccess::create([
            'gallery_id' => $gallery->id,
            'session_id' => $request->session()->getId(),
            'ip_address' => $request->ip(),
            'granted_at' => now(),
        ]);

        return redirect()->route('gallery.show', $slug);
    }

    public function destroyAccess(Request $request, string $slug): RedirectResponse
    {
        $gallery = Gallery::where('slug', $slug)->firstOrFail();
        $request->session()->forget("gallery_access_{$gallery->id}");

        return redirect()->route('gallery.show', $slug);
    }

    public function download(Request $request, string $slug, Photo $photo): StreamedResponse
    {
        $gallery = Gallery::where('slug', $slug)
            ->where('is_published', true)
            ->firstOrFail();

        abort_unless($photo->gallery_id === $gallery->id, 404);

        if ($gallery->isProtected()) {
            abort_unless(
                $request->session()->has("gallery_access_{$gallery->id}"),
                403,
                'Access denied. Please enter the gallery access code first.',
            );
        }

        $stream = $this->factory->make($gallery->photographer)->download($photo->drive_file_id);
        $filename = $photo->filename;
        $mimeType = $photo->mime_type ?? 'application/octet-stream';

        return response()->stream(
            function () use ($stream) {
                if (is_resource($stream)) {
                    fpassthru($stream);
                } else {
                    echo $stream->getContents();
                }
            },
            200,
            [
                'Content-Type' => $mimeType,
                'Content-Disposition' => 'attachment; filename="'.addslashes($filename).'"',
                'Cache-Control' => 'no-cache, no-store',
            ],
        );
    }

    public function preview(Request $request, string $slug, Photo $photo): StreamedResponse
    {
        $gallery = Gallery::where('slug', $slug)
            ->where('is_published', true)
            ->firstOrFail();

        abort_unless($photo->gallery_id === $gallery->id, 404);

        if ($gallery->isProtected()) {
            abort_unless(
                $request->session()->has("gallery_access_{$gallery->id}"),
                403,
                'Access denied.',
            );
        }

        $stream = $this->factory->make($gallery->photographer)->download($photo->drive_file_id);
        $mimeType = $photo->mime_type ?? 'image/jpeg';

        return response()->stream(
            function () use ($stream) {
                if (is_resource($stream)) {
                    fpassthru($stream);
                } else {
                    echo $stream->getContents();
                }
            },
            200,
            [
                'Content-Type' => $mimeType,
                'Content-Disposition' => 'inline; filename="'.addslashes($photo->filename).'"',
                'Cache-Control' => 'public, max-age=86400, stale-while-revalidate=604800',
            ],
        );
    }

    private function hasAccess(Request $request, Gallery $gallery): bool
    {
        return $request->session()->has("gallery_access_{$gallery->id}");
    }
}

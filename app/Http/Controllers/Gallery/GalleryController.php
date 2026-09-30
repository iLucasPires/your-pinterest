<?php

namespace App\Http\Controllers\Gallery;

use App\Http\Controllers\Controller;
use App\Models\Gallery\Gallery;
use App\Models\Gallery\Photo;
use App\Services\Gallery\GalleryAccessService;
use App\Services\Google\GoogleDriveProviderFactory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class GalleryController extends Controller
{
    public function __construct(
        private readonly GoogleDriveProviderFactory $factory,
        private readonly GalleryAccessService $access,
    ) {}

    public function show(Request $request, string $slug): View|RedirectResponse
    {
        $gallery = Gallery::query()
            ->with('client')
            ->where('slug', $slug)
            ->where('is_published', true)
            ->firstOrFail();

        $hasAccess = $this->access->canViewContent(
            $gallery,
            $request->user(),
            $request->session(),
        );

        $showLogin = $request->query('mode') === 'login';

        return view('pages.gallery', compact('gallery', 'hasAccess', 'showLogin'));
    }

    public function download(Request $request, string $slug, Photo $photo): RedirectResponse
    {
        $gallery = Gallery::query()
            ->with('client')
            ->where('slug', $slug)
            ->where('is_published', true)
            ->firstOrFail();

        abort_unless(
            $photo->gallery_id === $gallery->id,
            Response::HTTP_NOT_FOUND
        );

        abort_unless(
            $this->access->canViewContent(
                $gallery,
                $request->user(),
                $request->session()
            ),
            Response::HTTP_FORBIDDEN,
        );

        $provider = $this->factory->make($gallery->photographer);
        $downloadUrl = $provider->browserDownloadUrl($photo->drive_file_id);

        return redirect()->away($downloadUrl);
    }

    public function preview(Request $request, string $slug, Photo $photo): Response
    {
        return $this->variant($request, $slug, $photo, 'preview_path');
    }

    public function thumbnail(Request $request, string $slug, Photo $photo): Response
    {
        return $this->variant($request, $slug, $photo, 'thumbnail_path');
    }

    public function adminThumbnail(Request $request, Photo $photo): Response
    {
        $user = $request->user();
        $gallery = $photo->gallery;

        abort_unless(
            $user->id === $gallery->user_id,
            Response::HTTP_FORBIDDEN
        );

        return $this->storedVariant($photo, 'thumbnail_path');
    }

    private function variant(Request $request, string $slug, Photo $photo, string $attribute): Response
    {
        $gallery = Gallery::query()
            ->with('client')
            ->where('slug', $slug)
            ->where('is_published', true)
            ->firstOrFail();

        abort_unless(
            $photo->gallery_id === $gallery->id,
            Response::HTTP_NOT_FOUND
        );

        abort_unless(
            $this->access->canViewContent(
                $gallery,
                $request->user(),
                $request->session()
            ),
            Response::HTTP_FORBIDDEN,
        );

        return $this->storedVariant($photo, $attribute);
    }

    private function storedVariant(Photo $photo, string $attribute): Response
    {
        $disk = Storage::disk(config('photos.disk'));
        $path = $photo->getAttribute($attribute);

        if (! $path || ! $disk->exists($path)) {
            return response()->file(public_path('images/photo-pending.svg'), [
                'Content-Type' => 'image/svg+xml',
                'Cache-Control' => 'private, no-store',
            ]);
        }

        return $disk->response($path, 'photo.webp', [
            'Content-Type' => 'image/webp',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}

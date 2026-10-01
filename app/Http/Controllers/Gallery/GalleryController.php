<?php

namespace App\Http\Controllers\Gallery;

use App\Http\Controllers\Controller;
use App\Models\Gallery\Gallery;
use App\Models\Gallery\Photo;
use App\Services\Gallery\GalleryAccessService;
use App\Services\Google\GoogleDriveProviderFactory;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Throwable;
use ZipArchive;

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

    public function downloadArchive(Request $request, string $slug): BinaryFileResponse
    {
        $gallery = Gallery::query()
            ->with(['client', 'photographer'])
            ->where('slug', $slug)
            ->where('is_published', true)
            ->firstOrFail();

        abort_unless(
            $this->access->canViewContent($gallery, $request->user(), $request->session()),
            Response::HTTP_FORBIDDEN,
        );

        $temporaryDirectory = storage_path('app');
        $archivePath = tempnam($temporaryDirectory, 'gallery-');

        if ($archivePath === false) {
            throw new RuntimeException('Unable to create a temporary gallery archive.');
        }

        $archive = new ZipArchive;
        if ($archive->open($archivePath, ZipArchive::OVERWRITE) !== true) {
            unlink($archivePath);
            throw new RuntimeException('Unable to open a temporary gallery archive.');
        }

        $temporaryFiles = [];
        $usedFilenames = [];

        try {
            $provider = $this->factory->make($gallery->photographer);

            foreach ($gallery->photos()->orderBy('id')->cursor() as $photo) {
                $temporaryFilePath = tempnam($temporaryDirectory, 'gallery-photo-');

                if ($temporaryFilePath === false) {
                    throw new RuntimeException('Unable to create a temporary photo file.');
                }

                $temporaryFiles[] = $temporaryFilePath;
                $targetStream = Utils::streamFor(fopen($temporaryFilePath, 'wb'));

                try {
                    $photoStream = Utils::streamFor($provider->download($photo->drive_file_id));

                    try {
                        if ($photoStream->isSeekable()) {
                            $photoStream->rewind();
                        }

                        $copiedBytes = Utils::copyToStream($photoStream, $targetStream);

                        if ($copiedBytes === 0) {
                            throw new RuntimeException('Google Drive returned an empty photo file.');
                        }

                        if ($photo->size !== null && $copiedBytes !== $photo->size) {
                            throw new RuntimeException(sprintf(
                                'Google Drive returned an incomplete photo file: expected %d bytes, received %d.',
                                $photo->size,
                                $copiedBytes,
                            ));
                        }
                    } finally {
                        $photoStream->close();
                    }
                } finally {
                    $targetStream->close();
                }

                $filename = $this->uniqueArchiveFilename($photo->filename, $usedFilenames);
                $entryName = $gallery->slug.'/'.$filename;

                if (! $archive->addFile($temporaryFilePath, $entryName)) {
                    throw new RuntimeException('Unable to add a photo to the gallery archive.');
                }
            }

            if (! $archive->close()) {
                throw new RuntimeException('Unable to finish the gallery archive.');
            }
        } catch (Throwable $exception) {
            $archive->close();

            if (is_file($archivePath)) {
                unlink($archivePath);
            }

            throw $exception;
        } finally {
            foreach ($temporaryFiles as $temporaryFilePath) {
                unlink($temporaryFilePath);
            }
        }

        return response()
            ->download($archivePath, Str::slug($gallery->name).'.zip', [
                'Content-Type' => 'application/zip',
                'Cache-Control' => 'private, no-store',
            ])
            ->deleteFileAfterSend(true);
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

    /** @param array<string, true> $usedFilenames */
    private function uniqueArchiveFilename(string $filename, array &$usedFilenames): string
    {
        $filename = basename(str_replace('\\', '/', $filename));
        $filename = trim(preg_replace('/[\x00-\x1F\x7F]/', '_', $filename) ?? '');

        if ($filename === '' || $filename === '.' || $filename === '..') {
            $filename = 'photo';
        }

        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $baseName = $extension === '' ? $filename : substr($filename, 0, -strlen($extension) - 1);
        $candidate = $filename;
        $suffix = 2;

        while (isset($usedFilenames[strtolower($candidate)])) {
            $candidate = $baseName.' ('.$suffix++.')'.($extension === '' ? '' : '.'.$extension);
        }

        $usedFilenames[strtolower($candidate)] = true;

        return $candidate;
    }
}

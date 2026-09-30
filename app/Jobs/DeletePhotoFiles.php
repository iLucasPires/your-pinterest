<?php

namespace App\Jobs;

use App\Models\Gallery\Photo;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Storage;

class DeletePhotoFiles implements ShouldQueue
{
    use Queueable;

    public int $tries = 20;

    /** @var list<int> */
    public array $backoff = [30, 120, 300, 900];

    public function __construct(public int $galleryId, public int $photoId) {}

    /** @return list<WithoutOverlapping> */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('photo-variants:' . $this->photoId))
                ->shared()
                ->releaseAfter(30)
                ->expireAfter(330)
        ];
    }

    public function handle(): void
    {
        $photo = Photo::find($this->photoId);
        $disk = Storage::disk(config('photos.disk'));

        $directory = "galleries/{$this->galleryId}/photos/{$this->photoId}";

        $keep = $photo
            ? array_filter([
                $photo->thumbnail_path,
                $photo->preview_path,
            ])
            : [];

        $obsolete = array_values(
            array_diff($disk->allFiles($directory), $keep)
        );

        if ($obsolete) {
            $disk->delete($obsolete);
        }
    }
}

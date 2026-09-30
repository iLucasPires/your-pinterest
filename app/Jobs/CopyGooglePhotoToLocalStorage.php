<?php

namespace App\Jobs;

use App\Models\Gallery\Photo;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Compatibility for jobs queued before optimized variants were introduced. */
class CopyGooglePhotoToLocalStorage implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $photoId, public readonly ?string $driveModifiedAt) {}

    public function handle(): void
    {
        $photo = Photo::find($this->photoId);
        if ($photo) {
            GeneratePhotoVariants::dispatch($photo->id, $photo->variantSourceHash());
        }
    }
}

<?php

namespace App\Jobs;

use App\Actions\Gallery\SyncGalleryFromDrive;
use App\Models\Gallery\Gallery;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncGalleryJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $timeout = 120;

    public function __construct(
        public readonly int $galleryId,
    ) {}

    public function handle(SyncGalleryFromDrive $action): void
    {
        $gallery = Gallery::findOrFail($this->galleryId);
        $action->handle($gallery);
    }
}

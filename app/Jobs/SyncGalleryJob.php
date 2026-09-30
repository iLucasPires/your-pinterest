<?php

namespace App\Jobs;

use App\Actions\Gallery\SyncGalleryFromDrive;
use App\Models\Gallery\Gallery;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncGalleryJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    /** @var list<int> */
    public array $backoff = [30, 120, 300];

    public function uniqueId(): string
    {
        return (string) $this->galleryId;
    }

    public function __construct(
        public readonly int $galleryId,
    ) {}

    public function handle(SyncGalleryFromDrive $action): void
    {
        $gallery = Gallery::findOrFail($this->galleryId);
        $action->handle($gallery);
    }
}

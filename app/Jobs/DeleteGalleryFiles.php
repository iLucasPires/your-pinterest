<?php

namespace App\Jobs;

use App\Models\Gallery\Gallery;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class DeleteGalleryFiles implements ShouldQueue
{
    use Queueable;

    public int $tries = 20;

    /** @var list<int> */
    public array $backoff = [30, 120, 300, 900];

    public function __construct(public int $galleryId) {}

    public function handle(): void
    {
        if (Gallery::query()->whereKey($this->galleryId)->exists()) {
            return;
        }

        if (! Storage::disk(config('photos.disk'))->deleteDirectory("galleries/{$this->galleryId}")) {
            throw new RuntimeException('Unable to delete gallery files.');
        }
    }
}

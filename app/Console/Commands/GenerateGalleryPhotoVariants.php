<?php

namespace App\Console\Commands;

use App\Jobs\GeneratePhotoVariants;
use App\Models\Gallery\Photo;
use Illuminate\Console\Command;

class GenerateGalleryPhotoVariants extends Command
{
    protected $signature = 'photos:generate-variants {--gallery= : Limit to a gallery ID}';

    protected $description = 'Queue missing or outdated photo variants using persisted Drive metadata';

    public function handle(): int
    {
        $count = 0;
        Photo::query()
            ->when($this->option('gallery'), fn ($query, $id) => $query->where('gallery_id', $id))
            ->lazyById()->each(function (Photo $photo) use (&$count): void {
                if (! $photo->variantsAreCurrent()) {
                    GeneratePhotoVariants::dispatch($photo->id, $photo->variantSourceHash());
                    $count++;
                }
            });

        $this->info("Queued {$count} photos for processing.");

        return self::SUCCESS;
    }
}

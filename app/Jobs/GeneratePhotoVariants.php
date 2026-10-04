<?php

namespace App\Jobs;

use App\Models\Gallery\Photo;
use App\Services\Gallery\PhotoVariantGenerator;
use App\Services\Google\GoogleDriveProviderFactory;
use DateTimeInterface;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class GeneratePhotoVariants implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $timeout = 300;

    // Libera o lock de unicidade se o worker morrer (timeout, OOM, kill).
    public int $uniqueFor = 600;

    /** @var list<int> */
    public array $backoff = [30, 120, 300, 600, 900, 1800, 3600];

    public function __construct(public int $photoId, public string $sourceHash) {}

    public function uniqueId(): string
    {
        return $this->photoId . ':' . $this->sourceHash;
    }

    // Limita por tempo em vez de tentativas: release() do WithoutOverlapping
    // conta como attempt e estouraria $tries só esperando o lock.
    public function retryUntil(): DateTimeInterface
    {
        return now()->addHours(3);
    }

    /** @return list<WithoutOverlapping> */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('photo-variants:' . $this->photoId))
                ->shared()
                ->releaseAfter(30)
                ->expireAfter(330),
        ];
    }

    public function handle(GoogleDriveProviderFactory $factory, PhotoVariantGenerator $generator): void
    {
        $photo = Photo::query()
            ->with('gallery.photographer')
            ->find($this->photoId);

        if (! $photo || $photo->variantSourceHash() !== $this->sourceHash) {
            return;
        }

        if ($photo->variantsAreCurrent()) {
            DeletePhotoFiles::dispatch($photo->gallery_id, $photo->id)->afterCommit();

            return;
        }

        $temporary = tmpfile();
        if ($temporary === false) {
            throw new RuntimeException('Unable to create a temporary image file.');
        }

        $target = Utils::streamFor($temporary);
        $source = null;
        $paths = [];
        $variantPaths = [];
        $published = false;

        try {
            $source = Utils::streamFor(
                $factory
                    ->make($photo->gallery->photographer)
                    ->download($photo->drive_file_id)
            );
            Utils::copyToStream($source, $target);

            $target->rewind();
            $directory = "galleries/{$photo->gallery_id}/photos/{$photo->id}/" . Str::uuid();

            $temporaryPath = stream_get_meta_data($temporary)['uri'] ?? null;

            if (! is_string($temporaryPath)) {
                throw new RuntimeException('Unable to locate temporary image file.');
            }
            $paths = $generator->generate($temporaryPath, $directory);
            $variantPaths = array_intersect_key($paths, [
                'thumbnail_path' => true,
                'preview_path' => true,
            ]);

            $published = DB::transaction(function () use ($paths): bool {
                $current = Photo::query()
                    ->lockForUpdate()
                    ->find($this->photoId);

                if (! $current || $current->variantSourceHash() !== $this->sourceHash) {
                    return false;
                }

                $current->update($paths + [
                    'variants_source_hash' => $this->sourceHash,
                ]);

                return true;
            });

            if ($published) {
                DeletePhotoFiles::dispatch($photo->gallery_id, $photo->id)->afterCommit();
            }
        } finally {
            $target->close();
            $source?->close();

            if (! $published && $variantPaths) {
                Storage::disk(config('photos.disk'))->delete(array_values($variantPaths));
            }
        }
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('Unable to generate photo variants.', [
            'photo_id' => $this->photoId,
            'source_hash' => $this->sourceHash,
            'exception' => $exception,
        ]);
    }
}

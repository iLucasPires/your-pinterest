<?php

namespace App\Actions\Gallery;

use App\Jobs\GeneratePhotoVariants;
use App\Models\Gallery\Gallery;
use App\Models\Gallery\Photo;
use App\Services\Google\GoogleDriveProviderFactory;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Synchronises a gallery's Photo records with its Google Drive folder.
 *
 * - Creates photos that are new in Drive.
 * - Updates metadata for photos that exist in both.
 * - Removes photos that have been deleted from Drive.
 * - Queues optimized variants for new, changed or missing images.
 */
class SyncGalleryFromDrive
{
    public function __construct(
        private readonly GoogleDriveProviderFactory $factory,
    ) {}

    public function handle(Gallery $gallery): SyncResult
    {
        if (! $gallery->drive_folder_id) {
            throw new \RuntimeException("Gallery [{$gallery->id}] has no Drive folder set.");
        }

        $photographer = $gallery->photographer;
        $provider = $this->factory->make($photographer);
        $driveFiles = $provider->listFiles($gallery->drive_folder_id);

        $photosToProcess = [];

        $result = DB::transaction(function () use ($gallery, $driveFiles, &$photosToProcess): SyncResult {
            $driveIds = [];
            $added = 0;
            $updated = 0;

            foreach ($driveFiles as $index => $file) {
                $driveIds[] = $file->id;

                $photo = Photo::where('gallery_id', $gallery->id)
                    ->where('drive_file_id', $file->id)
                    ->first();

                $driveModifiedAt = $file->modifiedAt
                    ? Carbon::instance(\DateTime::createFromImmutable($file->modifiedAt))
                    : null;

                $data = [
                    'gallery_id' => $gallery->id,
                    'drive_file_id' => $file->id,
                    'filename' => $file->name,
                    'mime_type' => $file->mimeType,
                    'size' => $file->size,
                    'width' => $file->width,
                    'height' => $file->height,
                    'thumbnail_url' => null,
                    'drive_modified_at' => $driveModifiedAt,
                    'sort_order' => $index,
                ];

                if ($file->exifMetadata !== null) {
                    $data['exif_metadata'] = array_replace($photo?->exif_metadata ?? [], $file->exifMetadata);
                }

                if ($photo) {
                    $photo->update($data);
                    $updated++;
                } else {
                    $photo = Photo::create($data);
                    $added++;
                }

                if (! $photo->variantsAreCurrent()) {
                    $photosToProcess[] = [
                        'photo_id' => $photo->id,
                        'source_hash' => $photo->variantSourceHash(),
                    ];
                }
            }

            $removed = 0;
            Photo::where('gallery_id', $gallery->id)
                ->whereNotIn('drive_file_id', $driveIds)
                ->lazyById()->each(function (Photo $photo) use (&$removed): void {
                    $photo->delete();
                    $removed++;
                });

            $gallery->update(['last_synced_at' => now()]);

            return new SyncResult(added: $added, updated: $updated, removed: (int) $removed);
        });

        foreach ($photosToProcess as $photoToProcess) {
            GeneratePhotoVariants::dispatch(
                $photoToProcess['photo_id'],
                $photoToProcess['source_hash'],
            )->afterCommit();
        }

        return $result;
    }
}

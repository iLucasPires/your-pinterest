<?php

namespace App\Actions\Gallery;

use App\Models\Gallery\Gallery;
use App\Models\Gallery\Photo;
use App\Services\Google\GoogleDriveProviderFactory;
use Illuminate\Support\Facades\DB;

/**
 * Synchronises a gallery's Photo records with its Google Drive folder.
 *
 * - Creates photos that are new in Drive.
 * - Updates metadata for photos that exist in both.
 * - Removes photos that have been deleted from Drive.
 * - Does NOT download originals.
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
        $provider     = $this->factory->make($photographer);
        $driveFiles   = $provider->listFiles($gallery->drive_folder_id);

        $result = DB::transaction(function () use ($gallery, $driveFiles): SyncResult {
            $driveIds = [];
            $added    = 0;
            $updated  = 0;

            foreach ($driveFiles as $index => $file) {
                $driveIds[] = $file->id;

                $photo = Photo::where('gallery_id', $gallery->id)
                    ->where('drive_file_id', $file->id)
                    ->first();

                $data = [
                    'gallery_id'        => $gallery->id,
                    'drive_file_id'     => $file->id,
                    'filename'          => $file->name,
                    'mime_type'         => $file->mimeType,
                    'size'              => $file->size,
                    'width'             => $file->width,
                    'height'            => $file->height,
                    'thumbnail_url'     => $file->thumbnailUrl,
                    'drive_modified_at' => $file->modifiedAt
                        ? \Carbon\Carbon::instance(\DateTime::createFromImmutable($file->modifiedAt))
                        : null,
                    'sort_order'        => $index,
                ];

                if ($photo) {
                    $photo->update($data);
                    $updated++;
                } else {
                    Photo::create($data);
                    $added++;
                }
            }

            $removed = Photo::where('gallery_id', $gallery->id)
                ->whereNotIn('drive_file_id', $driveIds)
                ->delete();

            $gallery->update(['last_synced_at' => now()]);

            return new SyncResult(added: $added, updated: $updated, removed: (int) $removed);
        });

        return $result;
    }
}

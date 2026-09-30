<?php

namespace App\Filament\Resources\GalleryResource\Pages;

use App\Jobs\SyncGalleryJob;
use App\Models\Gallery\Gallery;
use App\Filament\Resources\GalleryResource;

use Filament\Resources\Pages\CreateRecord;

use Illuminate\Support\Facades\Auth;

class CreateGallery extends CreateRecord
{
    protected static string $resource = GalleryResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['user_id'] = Auth::id();

        return $data;
    }

    protected function afterCreate(): void
    {
        /** @var Gallery $gallery */
        $gallery = $this->record;

        if ($gallery->drive_folder_id) {
            SyncGalleryJob::dispatch($gallery->id);
        }
    }
}

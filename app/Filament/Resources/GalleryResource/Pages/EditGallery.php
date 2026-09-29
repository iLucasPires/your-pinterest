<?php

namespace App\Filament\Resources\GalleryResource\Pages;

use App\Jobs\SyncGalleryJob;
use App\Models\Gallery\Gallery;
use App\Actions\Gallery\SetGalleryAccessCode;
use App\Filament\Resources\GalleryResource;

use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditGallery extends EditRecord
{
    protected static string $resource = GalleryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        /** @var Gallery $gallery */
        $gallery = $this->record;

        $plainCode = $this->data['access_code_plain'] ?? null;

        if ($gallery->access_type === Gallery::ACCESS_CODE && $plainCode) {
            app(SetGalleryAccessCode::class)->handle($gallery, $plainCode);
        }

        $originalFolderId = $this->record->getOriginal('drive_folder_id');
        if ($gallery->drive_folder_id && $gallery->drive_folder_id !== $originalFolderId) {
            SyncGalleryJob::dispatch($gallery->id);
        }
    }
}

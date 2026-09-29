<?php

namespace App\Filament\Resources\GalleryResource\Pages;

use App\Jobs\SyncGalleryJob;
use App\Models\Gallery\Gallery;
use App\Actions\Gallery\SetGalleryAccessCode;
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

        $plainCode = $this->data['access_code_plain'] ?? null;

        if ($gallery->access_type === Gallery::ACCESS_CODE) {
            $code = $plainCode ?: SetGalleryAccessCode::generateCode();
            app(SetGalleryAccessCode::class)->handle($gallery, $code);

            if (! $plainCode) {
                session()->flash('generated_code', $code);
            }
        }

        if ($gallery->drive_folder_id) {
            SyncGalleryJob::dispatch($gallery->id);
        }
    }
}

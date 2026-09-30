<?php

namespace App\Filament\Resources\GalleryResource\Pages;

use App\Jobs\SyncGalleryJob;
use App\Filament\Resources\GalleryResource;

use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditGallery extends EditRecord
{
    protected static string $resource = GalleryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('photos')
                ->label('Fotos')
                ->icon('heroicon-o-photo')
                ->color('gray')
                ->url(fn(): string => GalleryResource::getUrl('photos', ['record' => $this->getRecord()])),

            Actions\DeleteAction::make()
                ->icon('heroicon-o-trash'),
        ];
    }

    protected function afterSave(): void
    {
        $gallery = $this->record;
        $isFolderIdChanged = $gallery->wasChanged('drive_folder_id');

        if ($gallery->drive_folder_id && $isFolderIdChanged) {
            SyncGalleryJob::dispatch($gallery->id);
        }
    }
}

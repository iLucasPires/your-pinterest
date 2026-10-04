<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\GalleryResource;
use App\Models\Gallery\Gallery;
use App\Models\User;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;

class GoogleDriveFolderUsage extends Widget
{
    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.google-drive-folder-usage';

    protected function getViewData(): array
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return ['folders' => [], 'syncedPhotoCount' => 0];
        }

        $galleries = $user->galleries()
            ->withCount('photos')
            ->whereNotNull('drive_folder_id')
            ->where('drive_folder_id', '!=', '')
            ->orderBy('drive_folder_name')
            ->orderBy('name')
            ->get(['id', 'name', 'drive_folder_id', 'drive_folder_name', 'last_synced_at']);

        $maxPhotoCount = max(1, (int) $galleries->max('photos_count'));

        $folders = $galleries->values()->map(function (Gallery $gallery) use ($maxPhotoCount): array {
            $photoCount = $gallery->photos_count;

            return [
                'name' => $gallery->name,
                'folderName' => $gallery->drive_folder_name ?: 'Drive folder',
                'photoCount' => $photoCount,
                'scalePercent' => $photoCount > 0 ? max(4, (int) round(($photoCount / $maxPhotoCount) * 100)) : 0,
                'lastSyncedAt' => $gallery->last_synced_at?->diffForHumans() ?? 'Not synced yet',
                'editUrl' => GalleryResource::getUrl('edit', ['record' => $gallery]),
                'driveUrl' => "https://drive.google.com/drive/folders/{$gallery->drive_folder_id}",
            ];
        });

        return [
            'folders' => $folders,
            'syncedPhotoCount' => $galleries->sum('photos_count'),
            'scaleMax' => $maxPhotoCount,
        ];
    }
}

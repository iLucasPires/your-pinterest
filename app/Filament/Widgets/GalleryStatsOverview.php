<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\ClientResource;
use App\Filament\Resources\GalleryResource;
use App\Models\Gallery\Photo;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class GalleryStatsOverview extends StatsOverviewWidget
{
    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return [];
        }

        $galleries = $user->galleries();
        $galleryCount = (clone $galleries)->count();
        $publishedCount = (clone $galleries)->where('is_published', true)->count();
        $draftCount = $galleryCount - $publishedCount;
        $photoCount = Photo::query()
            ->whereHas('gallery', fn (Builder $query) => $query->whereBelongsTo($user))
            ->count();
        $clientCount = $user->clients()->count();

        return [
            Stat::make('Galleries', number_format($galleryCount))
                ->description("{$publishedCount} published")
                ->descriptionIcon('heroicon-m-globe-alt')
                ->icon('heroicon-o-photo')
                ->url(GalleryResource::getUrl()),
            Stat::make('Drafts', number_format($draftCount))
                ->description('Not published yet')
                ->descriptionIcon('heroicon-m-pencil-square')
                ->icon('heroicon-o-document')
                ->color('warning')
                ->url(GalleryResource::getUrl()),
            Stat::make('Photos', number_format($photoCount))
                ->description('Across all your galleries')
                ->descriptionIcon('heroicon-m-camera')
                ->icon('heroicon-o-camera'),
            Stat::make('Clients', number_format($clientCount))
                ->description('In your client list')
                ->descriptionIcon('heroicon-m-user-group')
                ->icon('heroicon-o-users')
                ->url(ClientResource::getUrl()),
        ];
    }
}

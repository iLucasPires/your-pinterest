<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\GoogleDriveStorageOverview;
use App\Models\Google\GoogleConnection;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Widgets\Widget;
use Illuminate\Contracts\View\View;

class GoogleDrivePage extends Page
{
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-cloud';

    protected static ?string $navigationLabel = 'Google Drive';

    protected static ?string $title = 'Google Drive';

    protected static ?string $slug = 'google-drive';

    protected static ?int $navigationSort = 3;

    protected string $view = 'pages.filament.google-drive';

    public function getConnection(): ?GoogleConnection
    {
        return auth()->user()?->googleConnection;
    }

    public function getHeader(): ?View
    {
        return view('filament.pages.google-drive-header', [
            'isConnected' => $this->getConnection() !== null,
            'refreshUrl' => request()->fullUrl(),
        ]);
    }

    /**
     * @return array<class-string<Widget>>
     */
    protected function getHeaderWidgets(): array
    {
        if (! $this->getConnection()) {
            return [];
        }

        return [
            GoogleDriveStorageOverview::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 1;
    }
}

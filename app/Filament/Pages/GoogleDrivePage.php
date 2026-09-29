<?php

namespace App\Filament\Pages;

use BackedEnum;

use App\Models\Google\GoogleConnection;

use Filament\Pages\Page;

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
}

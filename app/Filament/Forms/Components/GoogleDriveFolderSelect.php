<?php

namespace App\Filament\Forms\Components;

use App\Models\User;
use App\Services\Google\GoogleDriveService;
use Filament\Forms\Components\Component;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;

class GoogleDriveFolderSelect extends Component
{
    protected string $view = 'components.filament.forms.components.google-drive-folder-select';

    protected ?string $searchPlaceholder = 'Search folders...';

    protected bool $showHierarchy = true;

    protected bool $allowRootSelection = true;

    public function searchPlaceholder(string $placeholder): static
    {
        $this->searchPlaceholder = $placeholder;

        return $this;
    }

    public function showHierarchy(bool $condition = true): static
    {
        $this->showHierarchy = $condition;

        return $this;
    }

    public function allowRootSelection(bool $condition = true): static
    {
        $this->allowRootSelection = $condition;

        return $this;
    }

    public function getSearchPlaceholder(): string
    {
        return $this->searchPlaceholder;
    }

    public function getShowHierarchy(): bool
    {
        return $this->showHierarchy;
    }

    public function getAllowRootSelection(): bool
    {
        return $this->allowRootSelection;
    }

    /**
     * Get the current user.
     */
    protected function getUser(): ?User
    {
        return Auth::user();
    }

    /**
     * Get the Google Drive service instance.
     */
    protected function getDriveService(): GoogleDriveService
    {
        return app(GoogleDriveService::class);
    }

    /**
     * Check if the user has a Google Drive connection.
     */
    public function hasConnection(): bool
    {
        $user = $this->getUser();

        if (! $user) {
            return false;
        }

        return $this->getDriveService()->hasConnection($user);
    }

    /**
     * Get the connection error message if any.
     */
    public function getConnectionError(): ?string
    {
        $user = $this->getUser();

        if (! $user) {
            return 'User not authenticated.';
        }

        if (! $this->hasConnection()) {
            return 'Google Drive not connected. Please connect your Google Drive account first.';
        }

        return null;
    }

    /**
     * Get root folders for initial display.
     *
     * @return array<int, array{id: string, name: string, parentId: string|null}>
     */
    public function getRootFolders(): array
    {
        $user = $this->getUser();

        if (! $user || ! $this->hasConnection()) {
            return [];
        }

        try {
            $folders = $this->getDriveService()->getRootFolders($user);

            return array_map(
                fn ($folder) => [
                    'id' => $folder->id,
                    'name' => $folder->name,
                    'parentId' => $folder->parentId,
                ],
                $folders,
            );
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Get subfolders for a parent folder.
     *
     * @return array<int, array{id: string, name: string, parentId: string|null}>
     */
    public function getSubfolders(string $parentId): array
    {
        $user = $this->getUser();

        if (! $user || ! $this->hasConnection()) {
            return [];
        }

        try {
            $folders = $this->getDriveService()->getSubfolders($user, $parentId);

            return array_map(
                fn ($folder) => [
                    'id' => $folder->id,
                    'name' => $folder->name,
                    'parentId' => $folder->parentId,
                ],
                $folders,
            );
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Search folders by name.
     *
     * @return array<int, array{id: string, name: string, parentId: string|null}>
     */
    public function searchFolders(string $query): array
    {
        $user = $this->getUser();

        if (! $user || ! $this->hasConnection()) {
            return [];
        }

        try {
            $folders = $this->getDriveService()->searchFolders($user, $query);

            return array_map(
                fn ($folder) => [
                    'id' => $folder->id,
                    'name' => $folder->name,
                    'parentId' => $folder->parentId,
                ],
                $folders,
            );
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Get folder path (breadcrumbs).
     *
     * @return array<int, array{id: string, name: string, parentId: string|null}>
     */
    public function getFolderPath(string $folderId): array
    {
        $user = $this->getUser();

        if (! $user || ! $this->hasConnection()) {
            return [];
        }

        try {
            $folders = $this->getDriveService()->getFolderPath($user, $folderId);

            return array_map(
                fn ($folder) => [
                    'id' => $folder->id,
                    'name' => $folder->name,
                    'parentId' => $folder->parentId,
                ],
                $folders,
            );
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Get the folder URL.
     */
    public function getFolderUrl(string $folderId): string
    {
        return $this->getDriveService()->getFolderUrl($folderId);
    }
}
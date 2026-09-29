<?php

namespace App\Services\Google;

use App\DTOs\DriveFolderDTO;
use App\Models\User;
use App\Services\Google\GoogleDriveProviderFactory;

/**
 * High-level service for Google Drive folder operations.
 *
 * Wraps GoogleDriveProvider and provides folder search, navigation,
 * and hierarchy utilities for the Filament folder selector.
 */
class GoogleDriveService
{
    public function __construct(
        private readonly GoogleDriveProviderFactory $factory,
    ) {}

    /**
     * Get a provider instance for the given user.
     *
     * @throws RuntimeException if user has no Google connection
     */
    private function getProvider(User $user): GoogleDriveProvider
    {
        return $this->factory->make($user);
    }

    /**
     * Check if the user has a valid Google Drive connection.
     */
    public function hasConnection(User $user): bool
    {
        return $user->googleConnection !== null;
    }

    /**
     * Get the root folders (top-level folders in "My Drive").
     *
     * @return DriveFolderDTO[]
     */
    public function getRootFolders(User $user): array
    {
        $provider = $this->getProvider($user);

        return $provider->listFolders('root');
    }

    /**
     * Get subfolders of a specific folder.
     *
     * @return DriveFolderDTO[]
     */
    public function getSubfolders(User $user, string $parentId): array
    {
        $provider = $this->getProvider($user);

        return $provider->listFolders($parentId);
    }

    /**
     * Search folders by name (across all accessible folders).
     *
     * @return DriveFolderDTO[]
     */
    public function searchFolders(User $user, string $query, int $limit = 50): array
    {
        $provider = $this->getProvider($user);

        $folders = $provider->searchFolders($query);

        return array_slice($folders, 0, $limit);
    }

    /**
     * Get a folder by its ID.
     */
    public function getFolder(User $user, string $folderId): ?DriveFolderDTO
    {
        return $this->getProvider($user)->getFolder($folderId);
    }

    /**
     * Get the full path (breadcrumb) for a folder.
     *
     * @return DriveFolderDTO[] Ordered from root to the target folder
     */
    public function getFolderPath(User $user, string $folderId): array
    {
        $path = [];
        $currentId = $folderId;

        while ($currentId) {
            $folder = $this->getFolder($user, $currentId);

            if (! $folder) {
                break;
            }

            array_unshift($path, $folder);
            $currentId = $folder->parentId;
        }

        return $path;
    }

    /**
     * Get folder suggestions for autocomplete (root folders + recent).
     *
     * @return DriveFolderDTO[]
     */
    public function getSuggestions(User $user, int $limit = 20): array
    {
        $folders = $this->getRootFolders($user);

        return array_slice($folders, 0, $limit);
    }

    /**
     * Build the web view URL for a folder.
     */
    public function getFolderUrl(string $folderId): string
    {
        return "https://drive.google.com/drive/folders/{$folderId}";
    }
}
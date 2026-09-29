<?php

namespace App\Services;

use App\DTOs\DriveFileDTO;
use App\DTOs\DriveFolderDTO;

/**
 * Abstraction over a cloud storage provider.
 * Actions depend only on this interface, not on Google SDK classes directly.
 */
interface StorageProvider
{
    /**
     * List folders accessible to the authenticated user.
     * If $parentId is provided, list only folders inside that parent.
     *
     * @return DriveFolderDTO[]
     */
    public function listFolders(?string $parentId = null): array;

    /**
     * List image files inside a folder.
     *
     * @return DriveFileDTO[]
     */
    public function listFiles(string $folderId): array;

    /**
     * Get metadata for a single file.
     */
    public function getFile(string $fileId): DriveFileDTO;

    /**
     * Return a short-lived thumbnail URL for a file.
     */
    public function getThumbnailUrl(string $fileId): ?string;

    /**
     * Stream the file content.
     *
     * @return resource|\GuzzleHttp\Psr7\Stream
     */
    public function download(string $fileId): mixed;
}

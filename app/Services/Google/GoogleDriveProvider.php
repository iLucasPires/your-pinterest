<?php

namespace App\Services\Google;

use App\DTOs\DriveFileDTO;
use App\DTOs\DriveFolderDTO;
use App\Models\Google\GoogleConnection;

use App\Services\StorageProvider;

use Google\Client;
use Google\Service\Drive;

class GoogleDriveProvider implements StorageProvider
{
    private Drive $drive;

    public function __construct(private readonly GoogleConnection $connection)
    {
        $client = new Client();
        $client->setClientId(config('services.google.client_id'));
        $client->setClientSecret(config('services.google.client_secret'));
        $client->setRedirectUri(config('services.google.redirect'));

        $accessToken = [
            'access_token'  => $this->connection->access_token,
            'refresh_token' => $this->connection->refresh_token,
            'expires_in'    => 3600,
            'token_type'    => 'Bearer',
        ];

        if ($this->connection->token_expires_at) {
            $accessToken['created'] = $this->connection->token_expires_at->copy()->subHour()->timestamp;
        }

        $client->setAccessToken($accessToken);

        // Transparently refresh the token when expired or expiring soon (within 60s).
        if (($this->connection->isExpired() || $client->isAccessTokenExpired()) && $this->connection->refresh_token) {
            $newToken = $client->fetchAccessTokenWithRefreshToken($this->connection->refresh_token);

            if (isset($newToken['access_token'])) {
                $updateData = [
                    'access_token'     => $newToken['access_token'],
                    'token_expires_at' => now()->addSeconds((int) ($newToken['expires_in'] ?? 3600)),
                ];

                if (! empty($newToken['refresh_token'])) {
                    $updateData['refresh_token'] = $newToken['refresh_token'];
                }

                $this->connection->update($updateData);
                $client->setAccessToken($newToken);
            }
        }

        $this->drive = new Drive($client);
    }

    /**
     * List folders (optionally scoped to a parent folder).
     *
     * @return DriveFolderDTO[]
     */
    public function listFolders(?string $parentId = null): array
    {
        $query = "mimeType = 'application/vnd.google-apps.folder' and trashed = false";

        if ($parentId) {
            $query .= " and '{$parentId}' in parents";
        }

        $files = $this->paginate($query, 'id, name, parents');

        return array_map(
            fn($file) => DriveFolderDTO::fromGoogleFile($file),
            $files,
        );
    }

    /**
     * Search folders by name across all accessible folders.
     * Results include the parent folder name in displayName (e.g. "fotografia / 01-03-2026").
     *
     * @return DriveFolderDTO[]
     */
    public function searchFolders(string $query): array
    {
        $searchQuery = "mimeType = 'application/vnd.google-apps.folder'"
            . " and trashed = false"
            . " and name contains '{$query}'";

        $files   = $this->paginate($searchQuery, 'id, name, parents');
        $folders = array_map(fn($f) => DriveFolderDTO::fromGoogleFile($f), $files);

        // Collect unique parent IDs (skip nulls / root)
        $parentIds = array_unique(array_filter(
            array_map(fn(DriveFolderDTO $f) => $f->parentId, $folders),
        ));

        // Fetch parent names in one pass
        $parentNames = [];
        foreach ($parentIds as $parentId) {
            try {
                $parent = $this->drive->files->get($parentId, [
                    'fields' => 'id, name',
                ]);
                $parentNames[$parentId] = $parent->getName();
            } catch (\Throwable) {
                // Root or shared drive — skip silently
            }
        }

        // Rebuild DTOs with enriched displayName
        return array_map(function (DriveFolderDTO $folder) use ($parentNames) {
            $parentName  = $folder->parentId ? ($parentNames[$folder->parentId] ?? null) : null;
            $displayName = $parentName ? "{$parentName} / {$folder->name}" : $folder->name;

            return new DriveFolderDTO(
                id: $folder->id,
                name: $folder->name,
                parentId: $folder->parentId,
                displayName: $displayName,
            );
        }, $folders);
    }


    /**
     * List image files inside a folder.
     *
     * @return DriveFileDTO[]
     */
    public function listFiles(string $folderId): array
    {
        $query = "'{$folderId}' in parents"
            . " and mimeType contains 'image/'"
            . " and trashed = false";

        $files = $this->paginate(
            $query,
            'id, name, mimeType, size, imageMediaMetadata, thumbnailLink, modifiedTime',
        );

        return array_map(
            fn($file) => DriveFileDTO::fromGoogleFile($file),
            $files,
        );
    }

    /**
     * Get metadata for a single file (images).
     */
    public function getFile(string $fileId): DriveFileDTO
    {
        $file = $this->drive->files->get($fileId, [
            'fields' => 'id, name, mimeType, size, imageMediaMetadata, thumbnailLink, modifiedTime',
        ]);

        return DriveFileDTO::fromGoogleFile($file);
    }

    /**
     * Get a single folder by its Drive ID.
     * Returns null if the ID doesn't correspond to a folder.
     */
    public function getFolder(string $folderId): ?DriveFolderDTO
    {
        try {
            $file = $this->drive->files->get($folderId, [
                'fields' => 'id, name, mimeType, parents',
            ]);

            if ($file->getMimeType() !== 'application/vnd.google-apps.folder') {
                return null;
            }

            return DriveFolderDTO::fromGoogleFile($file);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Return the thumbnail link from Drive metadata.
     */
    public function getThumbnailUrl(string $fileId): ?string
    {
        $file = $this->drive->files->get($fileId, ['fields' => 'thumbnailLink']);

        return $file->getThumbnailLink();
    }

    /**
     * Download a file and return the response stream.
     */
    public function download(string $fileId): mixed
    {
        $response = $this->drive->files->get($fileId, ['alt' => 'media']);

        return $response->getBody();
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /**
     * Paginate through all results for a Drive files.list query.
     *
     * @return \Google\Service\Drive\DriveFile[]
     */
    private function paginate(string $query, string $fields): array
    {
        $results    = [];
        $pageToken  = null;

        do {
            $params = [
                'q'          => $query,
                'fields'     => "nextPageToken, files({$fields})",
                'pageSize'   => 100,
                'orderBy'    => 'name',
            ];

            if ($pageToken) {
                $params['pageToken'] = $pageToken;
            }

            $response  = $this->drive->files->listFiles($params);
            $results   = array_merge($results, $response->getFiles());
            $pageToken = $response->getNextPageToken();
        } while ($pageToken);

        return $results;
    }
}

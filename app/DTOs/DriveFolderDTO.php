<?php

namespace App\DTOs;

/**
 * Represents a folder returned from Google Drive.
 */
final class DriveFolderDTO
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly ?string $parentId,
        public readonly string $displayName = '',
    ) {}

    public static function fromGoogleFile(\Google\Service\Drive\DriveFile $file): self
    {
        $parents = $file->getParents();
        $name    = $file->getName();

        return new self(
            id:          $file->getId(),
            name:        $name,
            parentId:    $parents ? $parents[0] : null,
            displayName: $name,
        );
    }
}

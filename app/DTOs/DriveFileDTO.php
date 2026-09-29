<?php

namespace App\DTOs;

/**
 * Represents a single file returned from Google Drive.
 */
final class DriveFileDTO
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $mimeType,
        public readonly ?int $size,
        public readonly ?int $width,
        public readonly ?int $height,
        public readonly ?string $thumbnailUrl,
        public readonly ?\DateTimeImmutable $modifiedAt,
    ) {}

    public static function fromGoogleFile(\Google\Service\Drive\DriveFile $file): self
    {
        $imageMediaMetadata = $file->getImageMediaMetadata();

        return new self(
            id:           $file->getId(),
            name:         $file->getName(),
            mimeType:     $file->getMimeType() ?? 'application/octet-stream',
            size:         $file->getSize() !== null ? (int) $file->getSize() : null,
            width:        $imageMediaMetadata?->getWidth(),
            height:       $imageMediaMetadata?->getHeight(),
            thumbnailUrl: $file->getThumbnailLink(),
            modifiedAt:   $file->getModifiedTime()
                ? new \DateTimeImmutable($file->getModifiedTime())
                : null,
        );
    }
}

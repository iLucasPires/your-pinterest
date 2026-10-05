<?php

namespace App\DTOs;

/**
 * Represents a single file returned from Google Drive.
 */
final class DriveFileDTO
{
    /** @param array<string, int|float|string>|null $exifMetadata */
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $mimeType,
        public readonly ?int $size,
        public readonly ?int $width,
        public readonly ?int $height,
        public readonly ?string $thumbnailUrl,
        public readonly ?\DateTimeImmutable $modifiedAt,
        public readonly ?array $exifMetadata = null,
    ) {}

    public static function fromGoogleFile(\Google\Service\Drive\DriveFile $file): self
    {
        $imageMediaMetadata = $file->getImageMediaMetadata();
        $metadata = array_filter([
            'camera_make' => $imageMediaMetadata?->getCameraMake(),
            'camera_model' => $imageMediaMetadata?->getCameraModel(),
            'lens' => $imageMediaMetadata?->getLens(),
            'iso' => $imageMediaMetadata?->getIsoSpeed(),
            'aperture' => $imageMediaMetadata?->getAperture(),
            'shutter_speed' => $imageMediaMetadata?->getExposureTime() !== null
                ? (string) $imageMediaMetadata->getExposureTime()
                : null,
            'focal_length_mm' => $imageMediaMetadata?->getFocalLength(),
            'captured_at' => $imageMediaMetadata?->getTime(),
        ], static fn (mixed $value): bool => $value !== null && $value !== '');

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
            exifMetadata: $metadata === [] ? null : $metadata,
        );
    }
}

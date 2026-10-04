<?php

namespace App\Services\Gallery;

use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;

class PhotoVariantGenerator
{
    /**
     * @return array{
     *     thumbnail_path: string,
     *     preview_path: string,
     *     exif_metadata: array<string, int|float|string>|null
     * }
     */
    public function generate(string $originalPath, string $directory): array
    {
        if (config('photos.thumbnail_width') < 1 || config('photos.preview_width') < 1) {
            throw new \RuntimeException('Photo variant widths must be positive.');
        }
        $dimensions = @getimagesize($originalPath);

        if ($dimensions === false) {
            throw new \RuntimeException('Unsupported or invalid photo.');
        }

        $memoryLimit = ini_parse_quantity(ini_get('memory_limit'));
        $estimatedBytes = $dimensions[0] * $dimensions[1] * 12 + 16 * 1024 * 1024;

        if ($memoryLimit > 0 && memory_get_usage(true) + $estimatedBytes > $memoryLimit) {
            throw new \RuntimeException('Insufficient PHP memory for this photo; increase the queue worker memory_limit.');
        }

        $disk = Storage::disk(config('photos.disk'));

        $paths = [
            'thumbnail_path' => $directory . '/thumbnail.webp',
            'preview_path' => $directory . '/preview.webp',
        ];

        $metadata = $this->extractExifMetadata($originalPath);

        try {
            $manager = new ImageManager(Driver::class, decodeAnimation: false, strip: true);
            $image = $manager->decodePath($originalPath);

            $widths = [
                'preview' => (int) config('photos.preview_width'),
                'thumbnail' => (int) config('photos.thumbnail_width')
            ];

            arsort($widths);

            foreach ($widths as $variant => $width) {
                $image->scaleDown(width: $width);
                $encoded = $image->encode(new WebpEncoder(quality: (int) config('photos.quality')));
                $stream = $encoded->toStream();

                try {
                    if ($disk->writeStream($paths[$variant . '_path'], $stream, ['visibility' => 'private']) === false) {
                        throw new \RuntimeException('Unable to store generated photo variant.');
                    }
                } finally {
                    fclose($stream);
                }
            }

            return $paths + ['exif_metadata' => $metadata];
        } catch (\Throwable $exception) {
            $disk->delete(array_values($paths));
            throw $exception;
        }
    }

    /**
     * @return array<string, int|float|string>|null
     */
    private function extractExifMetadata(string $path): ?array
    {
        if (! function_exists('exif_read_data')) {
            return null;
        }

        $data = @exif_read_data($path, null, true, false);

        if (! is_array($data)) {
            return null;
        }

        $ifd0 = $data['IFD0'] ?? [];
        $exif = $data['EXIF'] ?? [];
        $metadata = [
            'camera_make' => $this->stringValue($ifd0['Make'] ?? null),
            'camera_model' => $this->stringValue($ifd0['Model'] ?? null),
            'lens' => $this->stringValue(
                $exif['LensModel'] ?? $exif['UndefinedTag:0xA434'] ?? null
            ),
            'iso' => $this->integerValue(
                $exif['ISOSpeedRatings'] ?? $exif['PhotographicSensitivity'] ?? null
            ),
            'aperture' => $this->rationalValue($exif['FNumber'] ?? null),
            'shutter_speed' => $this->stringValue($exif['ExposureTime'] ?? null),
            'focal_length_mm' => $this->rationalValue($exif['FocalLength'] ?? null),
            'captured_at' => $this->stringValue($exif['DateTimeOriginal'] ?? null),
        ];

        $metadata = array_filter(
            $metadata,
            static fn(int|float|string|null $value): bool => $value !== null && $value !== '',
        );

        return $metadata === [] ? null : $metadata;
    }

    private function stringValue(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return trim($value);
    }

    private function integerValue(mixed $value): ?int
    {
        if (is_array($value)) {
            $value = reset($value);
        }

        return is_numeric($value) ? (int) $value : null;
    }

    private function rationalValue(mixed $value): ?float
    {
        if (is_array($value)) {
            $value = reset($value);
        }

        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        if (! is_string($value)) {
            return null;
        }

        if (preg_match('/^(-?\d+(?:\.\d+)?)\/(-?\d+(?:\.\d+)?)$/', $value, $matches) === 1) {
            $numerator = (float) $matches[1];
            $denominator = (float) $matches[2];

            return $denominator === 0.0 ? null : $numerator / $denominator;
        }

        return is_numeric($value) ? (float) $value : null;
    }
}

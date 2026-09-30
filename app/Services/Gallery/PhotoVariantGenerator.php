<?php

namespace App\Services\Gallery;

use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;

class PhotoVariantGenerator
{
    /** @return array{thumbnail_path: string, preview_path: string} */
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
        // GD needs a decoded raster plus working buffers for orientation/resizing.
        $estimatedBytes = $dimensions[0] * $dimensions[1] * 12 + 16 * 1024 * 1024;
        if ($memoryLimit > 0 && memory_get_usage(true) + $estimatedBytes > $memoryLimit) {
            throw new \RuntimeException('Insufficient PHP memory for this photo; increase the queue worker memory_limit.');
        }

        $disk = Storage::disk(config('photos.disk'));
        $paths = [
            'thumbnail_path' => $directory.'/thumbnail.webp',
            'preview_path' => $directory.'/preview.webp',
        ];

        try {
            $manager = new ImageManager(Driver::class, decodeAnimation: false, strip: true);
            $image = $manager->decodePath($originalPath);
            // Encode the larger variant first, then shrink the same image again.
            // Only one decoded photograph is held at a time.
            $widths = ['preview' => (int) config('photos.preview_width'), 'thumbnail' => (int) config('photos.thumbnail_width')];
            arsort($widths);
            foreach ($widths as $variant => $width) {
                $image->scaleDown(width: $width);
                $encoded = $image->encode(new WebpEncoder(quality: (int) config('photos.quality')));
                $stream = $encoded->toStream();

                try {
                    $disk->writeStream($paths[$variant.'_path'], $stream, ['visibility' => 'private']);
                } finally {
                    fclose($stream);
                }
            }

            return $paths;
        } catch (\Throwable $exception) {
            $disk->delete(array_values($paths));
            throw $exception;
        }
    }
}

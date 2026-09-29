<?php

namespace App\Actions\Gallery;

/**
 * Value object returned by SyncGalleryFromDrive.
 */
final class SyncResult
{
    public function __construct(
        public readonly int $added,
        public readonly int $updated,
        public readonly int $removed,
    ) {}

    public function total(): int
    {
        return $this->added + $this->updated;
    }

    public function toArray(): array
    {
        return [
            'added'   => $this->added,
            'updated' => $this->updated,
            'removed' => $this->removed,
        ];
    }
}

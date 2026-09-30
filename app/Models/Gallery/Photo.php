<?php

namespace App\Models\Gallery;

use App\Jobs\DeletePhotoFiles;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property int $gallery_id
 * @property string $drive_file_id
 * @property string $filename
 * @property string|null $mime_type
 * @property int|null $size
 * @property int|null $width
 * @property int|null $height
 * @property string|null $thumbnail_url
 * @property string|null $local_path
 * @property string|null $thumbnail_path
 * @property string|null $preview_path
 * @property string|null $variants_source_hash
 * @property Carbon|null $drive_modified_at
 * @property Carbon|null $local_drive_modified_at
 * @property string|null $notes
 * @property int|null $rating
 * @property int $sort_order
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Collection<int, PhotoTag> $tags
 * @property-read Gallery $gallery
 */
class Photo extends Model
{
    protected $fillable = [
        'gallery_id',
        'drive_file_id',
        'filename',
        'mime_type',
        'size',
        'width',
        'height',
        'thumbnail_url',
        'local_path',
        'thumbnail_path',
        'preview_path',
        'variants_source_hash',
        'drive_modified_at',
        'local_drive_modified_at',
        'notes',
        'rating',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'drive_modified_at' => 'datetime',
            'local_drive_modified_at' => 'datetime',
            'size' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'notes' => 'string',
            'rating' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected static function booted(): void
    {
        static::deleted(function (Photo $photo): void {
            DeletePhotoFiles::dispatch($photo->gallery_id, $photo->id)->afterCommit();
        });
    }

    public function variantSourceHash(): string
    {
        return hash('sha256', json_encode([
            $this->drive_file_id,
            $this->drive_modified_at?->format('Y-m-d H:i:s.u'),
            $this->size,
            $this->mime_type,
            config('photos.thumbnail_width'),
            config('photos.preview_width'),
            config('photos.quality'),
        ], JSON_THROW_ON_ERROR));
    }

    public function variantsAreCurrent(): bool
    {
        return $this->variants_source_hash === $this->variantSourceHash()
            && filled($this->thumbnail_path) && filled($this->preview_path)
            && Storage::disk(config('photos.disk'))->exists($this->thumbnail_path)
            && Storage::disk(config('photos.disk'))->exists($this->preview_path);
    }

    /** @return BelongsTo<Gallery, $this> */
    public function gallery(): BelongsTo
    {
        return $this->belongsTo(Gallery::class);
    }

    /** @return BelongsToMany<PhotoTag, $this> */
    public function photoTags(): BelongsToMany
    {
        return $this->belongsToMany(PhotoTag::class, 'photo_photo_tag', 'photo_id', 'photo_tag_id')->withTimestamps();
    }

    /** @return BelongsToMany<PhotoTag, $this> */
    public function tags(): BelongsToMany
    {
        return $this->photoTags();
    }

    /**
     * Aspect ratio as a float, or null if dimensions unknown.
     */
    public function aspectRatio(): ?float
    {
        if ($this->width && $this->height) {
            return $this->width / $this->height;
        }

        return null;
    }

    /**
     * URL for previewing/streaming the photo directly from the application.
     */
    public function previewUrl(): string
    {
        return route('gallery.photo.preview', [$this->gallery->slug, $this->id]);
    }

    /**
     * Browsing never falls back to Google Drive.
     */
    public function displayThumbnailUrl(): string
    {
        return route('gallery.photo.thumbnail', [$this->gallery->slug, $this->id]);
    }
}

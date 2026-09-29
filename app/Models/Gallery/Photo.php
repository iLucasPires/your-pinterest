<?php

namespace App\Models\Gallery;

use App\Models\Gallery\Gallery;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

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
 * @property Carbon|null $drive_modified_at
 * @property string|null $notes
 * @property int|null $rating
 * @property int $sort_order
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Collection<int, Tag> $tags
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
        'drive_modified_at',
        'notes',
        'rating',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'drive_modified_at' => 'datetime',
            'size'              => 'integer',
            'width'             => 'integer',
            'height'            => 'integer',
            'notes'             => 'string',
            'rating'            => 'integer',
            'sort_order'        => 'integer',
        ];
    }

    public function gallery(): BelongsTo
    {
        return $this->belongsTo(Gallery::class);
    }

    public function photoTags(): BelongsToMany
    {
        return $this->belongsToMany(PhotoTag::class, 'photo_photo_tag', 'photo_id', 'photo_tag_id')->withTimestamps();
    }

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
     * Get the best thumbnail URL with fallback to our direct preview stream.
     */
    public function displayThumbnailUrl(): string
    {
        if (!empty($this->thumbnail_url)) {
            return $this->thumbnail_url;
        }

        return $this->previewUrl();
    }
}


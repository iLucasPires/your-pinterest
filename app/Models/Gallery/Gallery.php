<?php

namespace App\Models\Gallery;

use App\Jobs\SyncGalleryDrivePermissions;
use App\Models\Client;
use App\Models\User;
use Database\Factories\GalleryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $user_id
 * @property int|null $client_id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property string|null $drive_folder_id
 * @property string|null $drive_folder_name
 * @property string $access_type
 * @property string|null $access_code_hash
 * @property bool $is_published
 * @property Carbon|null $published_at
 * @property Carbon|null $last_synced_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Gallery extends Model
{
    /** @use HasFactory<GalleryFactory> */
    use HasFactory;

    public const ACCESS_PUBLIC = 'public';

    public const ACCESS_LINK = 'link';

    public const ACCESS_PRIVATE = 'private';

    public const ACCESS_CODE = self::ACCESS_PRIVATE;

    protected $attributes = [
        'access_type' => self::ACCESS_PUBLIC,
        'is_published' => false,
    ];

    protected $fillable = [
        'user_id',
        'client_id',
        'name',
        'slug',
        'description',
        'drive_folder_id',
        'drive_folder_name',
        'access_type',
        'access_code_hash',
        'is_published',
        'published_at',
        'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'published_at' => 'datetime',
            'last_synced_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Gallery $gallery): void {
            $gallery->access_code_hash = null;
        });

        static::saved(function (Gallery $gallery): void {
            $accessChanged = $gallery->wasChanged([
                'client_id',
                'drive_folder_id',
                'access_type',
                'is_published',
            ]);

            if (! $gallery->wasRecentlyCreated && ! $accessChanged) {
                return;
            }

            if ($gallery->photographer?->googleConnection) {
                SyncGalleryDrivePermissions::dispatch($gallery->user_id)->afterCommit();
            }
        });

        static::deleted(function (Gallery $gallery): void {
            if ($gallery->photographer?->googleConnection) {
                SyncGalleryDrivePermissions::dispatch($gallery->user_id)->afterCommit();
            }
        });

        static::deleting(function (Gallery $gallery): void {
            $gallery->photos()->reorder()->lazyById()->each->delete();
        });

        static::creating(function (Gallery $gallery): void {
            if (empty($gallery->slug)) {
                $gallery->slug = static::generateUniqueSlug($gallery->name);
            }
        });
    }

    protected static function newFactory(): GalleryFactory
    {
        return GalleryFactory::new();
    }

    public static function generateUniqueSlug(string $name): string
    {
        $slug = Str::slug($name);
        $original = $slug;
        $count = 1;

        while (static::where('slug', $slug)->exists()) {
            $slug = "{$original}-{$count}";
            $count++;
        }

        return $slug;
    }

    /** @return BelongsTo<User, $this> */
    public function photographer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->photographer();
    }

    /** @return BelongsTo<Client, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /** @return HasMany<Photo, $this> */
    public function photos(): HasMany
    {
        return $this->hasMany(Photo::class)->orderBy('sort_order');
    }

    /** @param Builder<Gallery> $query
     * @return Builder<Gallery>
     */
    public function scopePubliclyDiscoverable(Builder $query): Builder
    {
        return $query->where('access_type', self::ACCESS_PUBLIC);
    }

    public function isProtected(): bool
    {
        return $this->isPrivate();
    }

    public function isPrivate(): bool
    {
        return $this->access_type === self::ACCESS_PRIVATE;
    }

    public function isPublic(): bool
    {
        return $this->access_type === self::ACCESS_PUBLIC;
    }

    public function publicUrl(): string
    {
        return route('gallery.show', $this->slug);
    }
}

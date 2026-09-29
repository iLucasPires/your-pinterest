<?php

namespace App\Models\Gallery;

use App\Models\Client;
use App\Models\User;

use Database\Factories\GalleryFactory;
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
    public const ACCESS_CODE   = 'code';

    protected $attributes = [
        'access_type'  => self::ACCESS_PUBLIC,
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
            'is_published'   => 'boolean',
            'published_at'   => 'datetime',
            'last_synced_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Gallery $gallery): void {
            if (empty($gallery->slug)) {
                $gallery->slug = static::generateUniqueSlug($gallery->name);
            }
        });
    }

    public static function generateUniqueSlug(string $name): string
    {
        $slug     = Str::slug($name);
        $original = $slug;
        $count    = 1;

        while (static::where('slug', $slug)->exists()) {
            $slug = "{$original}-{$count}";
            $count++;
        }

        return $slug;
    }

    public function photographer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function user(): BelongsTo
    {
        return $this->photographer();
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(Photo::class)->orderBy('sort_order');
    }

    public function isProtected(): bool
    {
        return $this->access_type === self::ACCESS_CODE;
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

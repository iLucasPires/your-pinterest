<?php

namespace App\Models;

use App\Jobs\SyncGalleryDrivePermissions;
use App\Models\Gallery\Gallery;

use Database\Factories\ClientFactory;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property string|null $email
 * @property string|null $notes
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Client extends Model
{
    /** @use HasFactory<ClientFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'email',
        'notes',
    ];

    protected static function booted(): void
    {
        static::updated(function (Client $client): void {
            if ($client->wasChanged('email') && $client->photographer?->googleConnection) {
                SyncGalleryDrivePermissions::dispatch($client->user_id)->afterCommit();
            }
        });

        static::deleted(function (Client $client): void {
            if ($client->photographer?->googleConnection) {
                SyncGalleryDrivePermissions::dispatch($client->user_id)->afterCommit();
            }
        });
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

    /** @return HasMany<Gallery, $this> */
    public function galleries(): HasMany
    {
        return $this->hasMany(Gallery::class);
    }

    /** @return BelongsTo<User, $this> */
    public function clientAccount(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_user_id');
    }
}

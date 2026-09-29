<?php

namespace App\Models;

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

    public function photographer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function user(): BelongsTo
    {
        return $this->photographer();
    }

    public function galleries(): HasMany
    {
        return $this->hasMany(Gallery::class);
    }
}

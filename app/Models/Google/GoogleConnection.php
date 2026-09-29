<?php

namespace App\Models\Google;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $google_id
 * @property string $google_email
 * @property string $access_token
 * @property string $refresh_token
 * @property Carbon|null $token_expires_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class GoogleConnection extends Model
{
    protected $fillable = [
        'user_id',
        'google_id',
        'google_email',
        'access_token',
        'refresh_token',
        'token_expires_at',
    ];

    protected function casts(): array
    {
        return [
            'access_token'     => 'encrypted',
            'refresh_token'    => 'encrypted',
            'token_expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Determine whether the access token has expired (with 60-second buffer).
     */
    public function isExpired(): bool
    {
        if ($this->token_expires_at === null) {
            return false;
        }

        return $this->token_expires_at->subMinute()->isPast();
    }
}

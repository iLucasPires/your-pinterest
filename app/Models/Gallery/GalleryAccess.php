<?php

namespace App\Models\Gallery;

use App\Models\Gallery\Gallery;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $gallery_id
 * @property string $session_id
 * @property string|null $ip_address
 * @property Carbon $granted_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class GalleryAccess extends Model
{
    protected $table = 'gallery_access';

    protected $fillable = [
        'gallery_id',
        'session_id',
        'ip_address',
        'granted_at',
    ];

    protected function casts(): array
    {
        return [
            'granted_at' => 'datetime',
        ];
    }

    public function gallery(): BelongsTo
    {
        return $this->belongsTo(Gallery::class);
    }
}

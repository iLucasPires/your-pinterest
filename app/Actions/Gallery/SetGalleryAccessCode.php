<?php

namespace App\Actions\Gallery;

use App\Models\Gallery\Gallery;
use Illuminate\Support\Facades\Hash;

class SetGalleryAccessCode
{
    /**
     * Hash and store a 6-digit access code on the gallery.
    * Pass null to clear the code without changing the selected access level.
     */
    public function handle(Gallery $gallery, ?string $plainCode): Gallery
    {
        $gallery->update([
            'access_type' => Gallery::ACCESS_PRIVATE,
            'access_code_hash' => $plainCode === null ? null : Hash::make($plainCode),
        ]);

        return $gallery->fresh();
    }

    /**
     * Generate a random 6-digit code as a zero-padded string.
     */
    public static function generateCode(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }
}

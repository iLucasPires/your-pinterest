<?php

namespace App\Actions\Gallery;

use App\Models\Gallery\Gallery;
use Illuminate\Support\Facades\Hash;

class SetGalleryAccessCode
{
    /**
     * Hash and store a 6-digit access code on the gallery.
     * Pass null to clear the code (and switch to public access).
     */
    public function handle(Gallery $gallery, ?string $plainCode): Gallery
    {
        if ($plainCode !== null) {
            $gallery->update([
                'access_type'      => Gallery::ACCESS_CODE,
                'access_code_hash' => Hash::make($plainCode),
            ]);
        } else {
            $gallery->update([
                'access_type'      => Gallery::ACCESS_PUBLIC,
                'access_code_hash' => null,
            ]);
        }

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

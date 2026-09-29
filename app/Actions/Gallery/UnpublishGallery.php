<?php

namespace App\Actions\Gallery;

use App\Models\Gallery\Gallery;

class UnpublishGallery
{
    public function handle(Gallery $gallery): Gallery
    {
        $gallery->update(['is_published' => false]);

        return $gallery->fresh();
    }
}

<?php

namespace App\Actions\Gallery;

use App\Models\Gallery\Gallery;

class PublishGallery
{
    public function handle(Gallery $gallery): Gallery
    {
        $gallery->update([
            'is_published' => true,
            'published_at' => $gallery->published_at ?? now(),
        ]);

        return $gallery->fresh();
    }
}

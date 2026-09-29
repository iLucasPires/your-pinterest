<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Gallery\Gallery;

class GalleryPolicy
{
    /**
     * Photographers can only manage their own galleries.
     */
    public function view(User $user, Gallery $gallery): bool
    {
        return $user->id === $gallery->user_id;
    }

    public function update(User $user, Gallery $gallery): bool
    {
        return $user->id === $gallery->user_id;
    }

    public function delete(User $user, Gallery $gallery): bool
    {
        return $user->id === $gallery->user_id;
    }
}

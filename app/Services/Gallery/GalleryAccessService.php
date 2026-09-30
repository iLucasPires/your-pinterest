<?php

namespace App\Services\Gallery;

use App\Models\Gallery\Gallery;
use App\Models\User;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Str;

class GalleryAccessService
{
    public function canViewContent(Gallery $gallery, ?User $user, ?Session $session = null): bool
    {
        return match ($gallery->access_type) {
            Gallery::ACCESS_PUBLIC, Gallery::ACCESS_LINK => true,
            Gallery::ACCESS_PRIVATE => $this->emailIsAuthorized($gallery, $user),
            default => false,
        };
    }

    public function isAuthorizedEmail(Gallery $gallery, string $email): bool
    {
        $authorizedEmail = $gallery->client?->email;

        return $gallery->client?->user_id === $gallery->user_id
            && filled($authorizedEmail)
            && Str::lower(trim($email)) === Str::lower(trim($authorizedEmail));
    }

    private function emailIsAuthorized(Gallery $gallery, ?User $user): bool
    {
        return $user !== null && $this->isAuthorizedEmail($gallery, $user->email);
    }
}

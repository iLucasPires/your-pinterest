<?php

namespace App\Actions\Google;

use App\Models\User;

/**
 * Removes the Google connection record for a user.
 */
class DisconnectGoogle
{
    public function handle(User $user): void
    {
        $user->googleConnection()?->delete();
    }
}

<?php

namespace App\Actions\Google;

use App\Models\User;
use App\Models\Google\GoogleConnection;
use Laravel\Socialite\Two\User as SocialiteUser;

/**
 * Creates or updates the Google connection record for a user
 * after they complete the OAuth flow.
 */
class UpsertGoogleConnection
{
    public function handle(User $user, SocialiteUser $socialiteUser): GoogleConnection
    {
        $data = [
            'google_id'        => $socialiteUser->getId(),
            'google_email'     => $socialiteUser->getEmail(),
            'access_token'     => $socialiteUser->token,
            'token_expires_at' => $socialiteUser->expiresIn
                ? now()->addSeconds((int) $socialiteUser->expiresIn)
                : null,
        ];

        if (! empty($socialiteUser->refreshToken)) {
            $data['refresh_token'] = $socialiteUser->refreshToken;
        }

        /** @var GoogleConnection $connection */
        $connection = GoogleConnection::updateOrCreate(
            ['user_id' => $user->id],
            $data,
        );

        return $connection;
    }
}

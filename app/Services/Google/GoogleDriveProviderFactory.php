<?php

namespace App\Services\Google;

use RuntimeException;

use App\Models\User;
use App\Models\Google\GoogleConnection;
use App\Services\Google\GoogleDriveProvider;

/**
 * Creates a GoogleDriveProvider for a given user.
 * Throws if the user has no active Google connection.
 */
class GoogleDriveProviderFactory
{
    public function make(User $user): GoogleDriveProvider
    {
        /** @var GoogleConnection|null $connection */
        $connection = $user->googleConnection;

        if (! $connection) {
            throw new RuntimeException(
                "User [{$user->id}] has no Google Drive connection.",
            );
        }

        return new GoogleDriveProvider($connection);
    }
}

<?php

namespace App\Jobs;

use App\Models\Gallery\Gallery;
use App\Models\User;
use App\Services\Google\GoogleDriveProviderFactory;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SyncGalleryDrivePermissions implements ShouldQueue
{
    use Queueable;

    // Reserved value: public permissions have no email address.
    private const PUBLIC_PERMISSION = 'anyone';

    public int $timeout = 120;

    public int $tries = 20;

    public array $backoff = [30, 120, 300];

    public function __construct(public int $userId) {}

    public function middleware(): array
    {
        $lockKey = 'drive-permissions:' . $this->userId;

        return [
            (new WithoutOverlapping($lockKey))
                ->releaseAfter(30)
                ->expireAfter(150),
        ];
    }

    public function handle(GoogleDriveProviderFactory $factory): void
    {
        $user = User::find($this->userId);

        if (! $user) {
            return;
        }

        $galleries = Gallery::query()
            ->with('client')
            ->where('user_id', $user->id)
            ->get();

        $desired = [];

        foreach ($galleries as $gallery) {
            $folderId = $gallery->drive_folder_id;
            if (! $folderId) {
                continue;
            }

            $isPublic = in_array($gallery->access_type, [Gallery::ACCESS_PUBLIC, Gallery::ACCESS_LINK], true)
                && $gallery->is_published;

            if ($isPublic) {
                $desired[$folderId . '|' . self::PUBLIC_PERMISSION] = [$folderId, self::PUBLIC_PERMISSION];
                continue;
            }

            if (! $gallery->isPrivate()) {
                continue;
            }

            $client = $gallery->client;
            $belongsToPhotographer = $client?->user_id === $user->id;

            if (! $folderId || ! $belongsToPhotographer) {
                continue;
            }

            $hasValidEmail = filter_var($client?->email, FILTER_VALIDATE_EMAIL);

            if (! $hasValidEmail) {
                continue;
            }

            $email = Str::lower(trim($client->email));
            $permissionKey = $folderId . '|' . $email;

            $desired[$permissionKey] = [$folderId, $email];
        }

        $records = DB::table('gallery_drive_permissions')
            ->where('user_id', $user->id)
            ->get();

        if ($galleries->isEmpty() && $records->isEmpty()) {
            return;
        }
        $provider = $factory->make($user);

        // Revoke public links even when they predate the application's tracking records.
        foreach ($galleries as $gallery) {
            $folderId = $gallery->drive_folder_id;
            $publicKey = $folderId . '|' . self::PUBLIC_PERMISSION;

            if ($folderId && ! isset($desired[$publicKey])) {
                $provider->syncPublicAccess($folderId, false);
            }
        }

        // Revoke only grants created by this application, and only when no gallery needs them.
        foreach ($records as $record) {
            $permissionKey = $record->folder_id . '|' . $record->email;

            if (isset($desired[$permissionKey])) {
                continue;
            }

            if ($record->managed) {
                if ($record->email === self::PUBLIC_PERMISSION) {
                    $provider->syncPublicAccess($record->folder_id, false);
                    DB::table('gallery_drive_permissions')->where('id', $record->id)->delete();
                    continue;
                }

                $permissionId = $record->permission_id
                    ?: $provider->readerPermission(
                        $record->folder_id,
                        $record->email
                    );

                if ($permissionId) {
                    $provider->revokeReader(
                        $record->folder_id,
                        $permissionId
                    );
                }
            }

            DB::table('gallery_drive_permissions')
                ->where('id', $record->id)
                ->delete();
        }

        foreach ($desired as [$folder, $email]) {
            $key = [
                'user_id' => $user->id,
                'folder_id' => $folder,
                'email' => $email,
            ];

            if ($email === self::PUBLIC_PERMISSION) {
                DB::table('gallery_drive_permissions')->updateOrInsert($key, [
                    'managed' => true,
                    'permission_id' => null,
                ]);

                $permissionId = $provider->syncPublicAccess($folder, true);

                DB::table('gallery_drive_permissions')->where($key)->update([
                    'permission_id' => $permissionId,
                ]);
                continue;
            }

            $record = DB::table('gallery_drive_permissions')
                ->where($key)
                ->first();

            $permission = $provider->readerPermission($folder, $email);

            if (! $permission) {
                // Record intent before the API call so a retry can recover a created grant.
                $pendingPermission = [
                    'managed' => true,
                    'permission_id' => null,
                ];

                DB::table('gallery_drive_permissions')->updateOrInsert($key, $pendingPermission);

                $permission = $provider->grantReader($folder, $email);
                $managed = true;
            } else {
                $managed = (bool) ($record->managed ?? false);
            }

            $permissionData = [
                'permission_id' => $permission,
                'managed' => $managed,
            ];

            DB::table('gallery_drive_permissions')->updateOrInsert($key, $permissionData);
        }
    }
}

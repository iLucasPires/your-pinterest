# Private galleries and Google Drive

Private galleries require an authenticated account matching the linked client's email.
Access codes and legacy code sessions no longer authorize access. Existing galleries
without a client must be linked to a client with an email address.

Run `php artisan migrate`, then reconnect the photographer's Google account. The
previous `drive.readonly` grant cannot create permissions; the new OAuth request uses
`drive`. Keep a queue worker running. Reconnecting enqueues reconciliation for existing
private galleries as well as new ones. Inspect `php artisan queue:failed` if sharing fails.

Saving a gallery, changing a client's email, or deleting either enqueues permission
reconciliation. The application grants `reader` access to the entire selected folder,
without notification emails. Use a dedicated folder containing only that client's files.
Changes take effect on Drive after the queue processes them. Shared Drive/domain policies
may reject sharing. Existing manual or inherited permissions are preserved: other manual user/group/domain access remains unchanged.

Only app-managed grants are revoked when no private gallery for that photographer
needs them. Do not manage the same folder through multiple photographer accounts.

All downloads redirect to Drive's `webContentLink`. The visitor must be signed into
Google with the authorized email (including when signing into the gallery with a local
password). Google may show a sign-in or download confirmation screen. Original bytes do
not pass through Laravel. Published public/link galleries also redirect to Drive, with public link access synchronized by the queue.

Focused validation:
`php artisan test --filter="DrivePermissionsTest|GalleryAccessLevelsTest|AccessCodeTest|PhotoVariantsTest|GalleryPublicAccessTest"`

## Public sharing synchronization

Published public/link galleries now receive an `anyone` reader permission with
`allowFileDiscovery=false`. Unpublished galleries do not receive a public link.
Switching to private or unpublishing removes public permissions on the folder,
including pre-existing public links. Inherited public access that cannot be removed
causes the job to fail; correct parent sharing in Drive and retry the job. The app
never changes the parent folder. Other manual user/group/domain shares and file-level
shares are not removed by this folder synchronization.

Public grants are tracked with the reserved value `anyone` in the permission table's
email column. Saving access/publication changes queues synchronization. Existing
records are reconciled when the photographer reconnects Google. Keep workers running
and inspect failed jobs. There is a delay between saving the gallery and Drive applying
the change; setting private locally is not proof that Drive permissions were revoked.

# Gallery image delivery

Google Drive owns the originals. Synchronization lists metadata once per folder
page and queues `GeneratePhotoVariants` only for new, changed or missing variants.
The existing OAuth/provider integration is unchanged.

Each job streams one original into an OS temporary file, uses Intervention Image 4
with GD to orient and resize it, writes WebP derivatives through Laravel Storage,
then closes/deletes the temporary original in `finally`. Animated inputs use their
first frame. GD/WebP support is required. Unsupported inputs fail independently.

`config/photos.php` defines the disk, thumbnail width (500), preview width (1920)
and quality (82). Environment overrides: `PHOTO_THUMBNAIL_WIDTH`,
`PHOTO_PREVIEW_WIDTH`, `PHOTO_WEBP_QUALITY`. Widths must be positive; quality 0–100.
Resizing preserves aspect ratio and never enlarges smaller photographs.

The `photos` disk defaults to `storage/app/private/photos`. Relative paths are:

```
galleries/{gallery_id}/photos/{photo_id}/{generation}/thumbnail.webp
galleries/{gallery_id}/photos/{photo_id}/{generation}/preview.webp
```

Generation directories allow both variants to finish before publishing their
paths in one database transaction. An old job cannot publish over newer metadata
or a deleted photo. The source hash includes Drive ID, modified timestamp (with
microseconds), size, MIME type and variant settings. Changing settings regenerates
variants on the next sync/backfill. No metadata request is needed per photo.

Grid, homepage and admin use thumbnails. The lightbox requests the preview only
while open. Both use application routes and never fall back to Drive. Missing
variants return a local placeholder, without queuing work during page requests.
Only the explicit original-download route reads the original from Drive.

The disk has no public URL or automatic serving route. All gallery image routes
check publication, photo/gallery membership and existing gallery access rules.
Admin thumbnails require the authenticated gallery owner, including draft albums.
Responses use `private, no-store` so changing public/private access does not leave
publicly cacheable responses. Storage can later use a private S3/R2 disk without
changing image-processing or authorization logic.

New variants replace old paths only after successful processing. Old derivatives
and legacy original copies in each photo directory are removed by retryable
`DeletePhotoFiles` jobs. Cleanup reads current paths from the database and shares
the generation lock, so it can also clean files created after a stale model was
loaded for deletion. A repeated completed generation job retries cleanup without
downloading again. Photo deletion and gallery deletion dispatch cleanup after commit; sync uses
model deletion rather than bulk SQL so the hooks run. Do not bulk-delete models
through raw SQL if file cleanup is required. Temporary/partially generated files
are cleaned on exceptions. A forced process/host crash can leave unpublished
derivative files; they are never served because no photo points to them and are
removed by the next cleanup for that photo.

The previous `gallery_photos` disk is retained solely for legacy file cleanup;
its automatic serving is disabled. Previously queued copy jobs now queue variant
generation instead of writing originals. Old unreferenced files from the previous
implementation under current photo directories are removed too. Audit directories
for records deleted before this change before removing the legacy disk entirely.

## Applying the change

```
composer install
php artisan migrate
php artisan queue:restart
php artisan photos:generate-variants
php -d memory_limit=512M artisan queue:work --timeout=300 --memory=512
npm run build
```

Use `photos:generate-variants --gallery=ID` to backfill one gallery without listing
Drive metadata. Normal gallery sync also fills missing variants. Until the worker
finishes, previously unprocessed photos show placeholders. No originals are
downloaded by the migration itself.

Keep `QUEUE_CONNECTION=database` (current setup) or another asynchronous driver.
Use one image-processing worker initially to limit Drive pressure and RAM usage.
The job timeout is 300 seconds; queue reservation `retry_after` must be greater
(database/Redis default here: 360). If using another connection, set its equivalent
reservation/visibility timeout accordingly. For very large camera files, increase
PHP memory beyond 512 MB as needed; the processor checks a conservative raster
memory estimate and throws a recoverable error if the configured limit is too low.

Jobs are unique by photo/version and serialize concurrent processing of one photo.
Failures retry with delays of 30, 120, 300, 600, 900, 1800 and 3600 seconds. Existing
Drive transient/429 retries remain in place. Terminal failures are logged with
photo ID and version and appear in Laravel's failed jobs; use `queue:retry` after
correcting the cause. One failed photo does not block other queued photos.

Run regression tests with:

```
php -d memory_limit=512M vendor/bin/phpunit tests/Feature/Gallery/PhotoVariantsTest.php
```

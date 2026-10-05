<?php

namespace Tests\Feature\Gallery;

use App\Actions\Gallery\SyncGalleryFromDrive;
use App\DTOs\DriveFileDTO;
use App\Jobs\DeletePhotoFiles;
use App\Jobs\GeneratePhotoVariants;
use App\Models\Client;
use App\Models\Gallery\Gallery;
use App\Models\Gallery\Photo;
use App\Models\User;
use App\Services\Gallery\PhotoVariantGenerator;
use App\Services\Google\GoogleDriveProvider;
use App\Services\Google\GoogleDriveProviderFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class PhotoVariantsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['photos.disk' => 'photos']);
        Storage::fake('photos');
        Storage::fake('gallery_photos');
        Queue::fake();
    }

    public function test_photo_variants_use_the_configured_filesystem_disk(): void
    {
        config(['photos.disk' => 's3']);
        Storage::fake('s3');

        $photo = $this->photo();
        (new GeneratePhotoVariants($photo->id, $photo->variantSourceHash()))
            ->handle($this->downloadFactory($this->jpeg(100, 75)), new PhotoVariantGenerator);

        $photo->refresh();

        Storage::disk('s3')->assertExists($photo->thumbnail_path);
        Storage::disk('s3')->assertExists($photo->preview_path);
        $this->assertTrue($photo->variantsAreCurrent());
        $this->assertEmpty(Storage::disk('photos')->allFiles());
    }

    public function test_job_generates_webp_variants_and_skips_duplicate_processing(): void
    {
        $photo = $this->photo();
        $factory = $this->downloadFactory($this->jpeg(2400, 1600));
        $job = new GeneratePhotoVariants($photo->id, $photo->variantSourceHash());
        $job->handle($factory, new PhotoVariantGenerator);
        $photo->refresh();

        foreach (['thumbnail' => [500, 333], 'preview' => [1920, 1280]] as $variant => $dimensions) {
            $bytes = Storage::disk('photos')->get($photo->{$variant.'_path'});
            $info = getimagesizefromstring($bytes);
            $this->assertSame('image/webp', $info['mime']);
            $this->assertEquals($dimensions, [$info[0], $info[1]]);
        }

        $this->assertNull($photo->local_path);
        $this->assertNull($photo->exif_metadata);
        $this->assertCount(2, Storage::disk('photos')->allFiles());
        $this->assertTrue($photo->variantsAreCurrent());
        $job->handle($factory, new PhotoVariantGenerator); // download expected once
    }

    public function test_exif_metadata_is_saved_with_generated_variants(): void
    {
        $photo = $this->photo();

        (new GeneratePhotoVariants($photo->id, $photo->variantSourceHash()))
            ->handle($this->downloadFactory($this->jpegWithExif()), new PhotoVariantGenerator);

        $this->assertEquals([
            'camera_make' => 'Canon',
            'camera_model' => 'EOS R5',
            'lens' => 'RF 50mm F1.8 STM',
            'iso' => 200,
            'aperture' => 2.8,
            'shutter_speed' => '1/125',
            'focal_length_mm' => 50.0,
            'captured_at' => '2026:10:04 10:30:00',
        ], $photo->fresh()->exif_metadata);
    }

    public function test_generation_preserves_drive_exif_when_original_has_no_exif(): void
    {
        $photo = $this->photo();
        $metadata = ['camera_make' => 'Canon', 'iso' => 200];
        $photo->update(['exif_metadata' => $metadata]);

        (new GeneratePhotoVariants($photo->id, $photo->variantSourceHash()))
            ->handle($this->downloadFactory($this->jpeg(100, 75)), new PhotoVariantGenerator);

        $this->assertSame($metadata, $photo->fresh()->exif_metadata);
    }

    public function test_small_images_are_not_upscaled(): void
    {
        $photo = $this->photo();
        (new GeneratePhotoVariants($photo->id, $photo->variantSourceHash()))
            ->handle($this->downloadFactory($this->jpeg(100, 75)), new PhotoVariantGenerator);

        foreach (['thumbnail_path', 'preview_path'] as $attribute) {
            $info = getimagesizefromstring(Storage::disk('photos')->get($photo->fresh()->$attribute));
            $this->assertSame([100, 75], [$info[0], $info[1]]);
        }
    }

    public function test_changed_source_replaces_variants_and_cleans_old_files_and_legacy_original(): void
    {
        $photo = $this->photo();
        $photo->update(['local_path' => 'legacy.jpg']);
        Storage::disk('gallery_photos')->put('legacy.jpg', 'old-original');
        (new GeneratePhotoVariants($photo->id, $photo->variantSourceHash()))
            ->handle($this->downloadFactory($this->jpeg(100, 75)), new PhotoVariantGenerator);
        Queue::assertPushed(DeletePhotoFiles::class, function ($job): bool {
            $job->handle();

            return true;
        });
        Storage::disk('gallery_photos')->assertMissing('legacy.jpg');

        $photo->refresh();
        $oldPaths = [$photo->thumbnail_path, $photo->preview_path];
        $photo->update(['drive_modified_at' => now()->addHour()]);
        $this->assertFalse($photo->variantsAreCurrent());
        (new GeneratePhotoVariants($photo->id, $photo->variantSourceHash()))
            ->handle($this->downloadFactory($this->jpeg(120, 90)), new PhotoVariantGenerator);
        Queue::assertPushed(DeletePhotoFiles::class, function ($job): bool {
            $job->handle();

            return true;
        });
        Storage::disk('photos')->assertMissing($oldPaths);
        $this->assertTrue($photo->fresh()->variantsAreCurrent());
    }

    public function test_failed_conversion_keeps_previous_variants_and_deletes_temporary_original(): void
    {
        $photo = $this->photo();
        $photo->update(['preview_path' => 'old.webp']);
        Storage::disk('photos')->put('old.webp', 'old');
        $temporaryPath = null;
        $generator = Mockery::mock(PhotoVariantGenerator::class);
        $generator->shouldReceive('generate')->once()->andReturnUsing(function ($path) use (&$temporaryPath) {
            $temporaryPath = $path;
            $this->assertFileExists($path);
            throw new \RuntimeException('conversion failed');
        });

        try {
            (new GeneratePhotoVariants($photo->id, $photo->variantSourceHash()))
                ->handle($this->downloadFactory('invalid-image'), $generator);
            $this->fail('Expected conversion error');
        } catch (\RuntimeException $exception) {
            $this->assertSame('conversion failed', $exception->getMessage());
        }

        $this->assertFileDoesNotExist($temporaryPath);
        $this->assertSame('old.webp', $photo->fresh()->preview_path);
        Storage::disk('photos')->assertExists('old.webp');
    }

    public function test_stale_job_does_not_download_and_deleted_photo_cleans_files(): void
    {
        $photo = $this->photo();
        $factory = Mockery::mock(GoogleDriveProviderFactory::class);
        $factory->shouldNotReceive('make');
        (new GeneratePhotoVariants($photo->id, 'obsolete'))->handle($factory, new PhotoVariantGenerator);
        $directory = "galleries/{$photo->gallery_id}/photos/{$photo->id}/old";
        $photo->update(['thumbnail_path' => $directory.'/thumb.webp', 'preview_path' => $directory.'/preview.webp']);
        Storage::disk('photos')->put($photo->thumbnail_path, 'thumb');
        Storage::disk('photos')->put($photo->preview_path, 'preview');
        $photo->delete();
        Queue::assertPushed(DeletePhotoFiles::class, function ($job): bool {
            $job->handle();

            return true;
        });
        $this->assertEmpty(Storage::disk('photos')->allFiles());
    }

    public function test_normal_browsing_never_uses_drive_even_when_derivatives_are_missing(): void
    {
        $photo = $this->photo();
        $factory = Mockery::mock(GoogleDriveProviderFactory::class);
        $factory->shouldNotReceive('make');
        $this->app->instance(GoogleDriveProviderFactory::class, $factory);
        foreach (['thumbnail', 'preview'] as $variant) {
            $this->get(route('gallery.photo.'.$variant, [$photo->gallery->slug, $photo->id]))
                ->assertOk()->assertHeader('Content-Type', 'image/svg+xml');
        }
        $this->get(route('gallery.show', $photo->gallery->slug))->assertOk();
    }

    public function test_private_variants_require_access_and_original_download_still_uses_drive(): void
    {
        $photo = $this->photo();
        $photo->gallery->update(['access_type' => Gallery::ACCESS_PRIVATE]);
        $photo->update(['thumbnail_path' => 'thumb.webp', 'preview_path' => 'preview.webp']);
        Storage::disk('photos')->put('thumb.webp', 'thumb');
        Storage::disk('photos')->put('preview.webp', 'preview');
        foreach (['thumbnail', 'preview'] as $variant) {
            $url = route('gallery.photo.'.$variant, [$photo->gallery->slug, $photo->id]);
            $this->get($url)->assertForbidden();
        }

        $client = Client::factory()->create(['user_id' => $photo->gallery->user_id, 'email' => 'download@example.test']);
        $photo->gallery->update(['client_id' => $client->id]);
        $this->actingAs(User::factory()->create(['email' => $client->email]));
        foreach (['thumbnail' => 'thumb', 'preview' => 'preview'] as $variant => $bytes) {
            $this->get(route('gallery.photo.'.$variant, [$photo->gallery->slug, $photo->id]))
                ->assertOk()->assertHeader('Content-Type', 'image/webp')->assertStreamedContent($bytes);
        }
        $provider = Mockery::mock(GoogleDriveProvider::class);
        $provider->shouldNotReceive('download');
        $provider->shouldReceive('browserDownloadUrl')->once()->with('file-1')
            ->andReturn('https://drive.google.com/download/example');
        $factory = Mockery::mock(GoogleDriveProviderFactory::class);
        $factory->shouldReceive('make')->once()->andReturn($provider);
        $this->app->instance(GoogleDriveProviderFactory::class, $factory);
        $this->get(route('gallery.photo.download', [$photo->gallery->slug, $photo->id]))
            ->assertRedirect('https://drive.google.com/download/example');
    }

    public function test_sync_skips_unchanged_photos_and_repairs_missing_derivatives(): void
    {
        $photo = $this->photo();
        $photo->gallery->update(['drive_folder_id' => 'folder']);
        (new GeneratePhotoVariants($photo->id, $photo->variantSourceHash()))
            ->handle($this->downloadFactory($this->jpeg(100, 75)), new PhotoVariantGenerator);
        $photo->refresh();
        $provider = Mockery::mock(GoogleDriveProvider::class);
        $provider->shouldReceive('listFiles')->with('folder')->andReturn([new DriveFileDTO(
            id: 'file-1', name: 'photo.jpg', mimeType: 'image/jpeg', size: null,
            width: null, height: null, thumbnailUrl: null, modifiedAt: null,
        )]);
        $provider->shouldNotReceive('download');
        $factory = Mockery::mock(GoogleDriveProviderFactory::class);
        $factory->shouldReceive('make')->andReturn($provider);
        $action = new SyncGalleryFromDrive($factory);
        $action->handle($photo->gallery);
        $action->handle($photo->gallery);
        Queue::assertNotPushed(GeneratePhotoVariants::class);
        Storage::disk('photos')->delete($photo->thumbnail_path);
        $action->handle($photo->gallery);
        Queue::assertPushed(GeneratePhotoVariants::class, 1);
    }

    public function test_version_changed_during_processing_discards_stale_output(): void
    {
        $photo = $this->photo();
        $generator = Mockery::mock(PhotoVariantGenerator::class);
        $generator->shouldReceive('generate')->once()->andReturnUsing(function ($path, $directory) use ($photo) {
            $paths = (new PhotoVariantGenerator)->generate($path, $directory);
            $photo->update(['drive_modified_at' => now()]);

            return $paths;
        });
        (new GeneratePhotoVariants($photo->id, $photo->variantSourceHash()))
            ->handle($this->downloadFactory($this->jpeg(100, 75)), $generator);

        $this->assertNull($photo->fresh()->preview_path);
        $this->assertEmpty(Storage::disk('photos')->allFiles());
    }

    public function test_cleanup_with_stale_model_removes_newly_published_files_after_deletion(): void
    {
        $stale = $this->photo();
        (new GeneratePhotoVariants($stale->id, $stale->variantSourceHash()))
            ->handle($this->downloadFactory($this->jpeg(100, 75)), new PhotoVariantGenerator);
        $stale->delete();
        (new DeletePhotoFiles($stale->gallery_id, $stale->id))->handle();
        $this->assertEmpty(Storage::disk('photos')->allFiles());
    }

    public function test_second_variant_write_failure_removes_partial_output(): void
    {
        $photo = $this->photo();
        $disk = Storage::disk('photos');
        $failingDisk = Mockery::mock($disk);
        $failingDisk->shouldReceive('writeStream')->once()->withArgs(function ($path) {
            return str_ends_with($path, '/preview.webp');
        })->andReturnUsing(fn ($path, $stream, $options) => $disk->writeStream($path, $stream, $options));
        $failingDisk->shouldReceive('writeStream')->once()->withArgs(function ($path) {
            return str_ends_with($path, '/thumbnail.webp');
        })->andReturn(false);
        Storage::set('photos', $failingDisk);

        try {
            (new GeneratePhotoVariants($photo->id, $photo->variantSourceHash()))
                ->handle($this->downloadFactory($this->jpeg(100, 75)), new PhotoVariantGenerator);
            $this->fail('Expected storage failure');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Unable to store generated photo variant.', $exception->getMessage());
        }
        $this->assertEmpty($disk->allFiles());
        $this->assertNull($photo->fresh()->variants_source_hash);
    }

    public function test_drive_rate_limit_is_retryable_without_publishing_files(): void
    {
        $photo = $this->photo();
        $provider = Mockery::mock(GoogleDriveProvider::class);
        $provider->shouldReceive('download')->once()->andThrow(new \RuntimeException('Too Many Requests', 429));
        $factory = Mockery::mock(GoogleDriveProviderFactory::class);
        $factory->shouldReceive('make')->once()->andReturn($provider);
        $job = new GeneratePhotoVariants($photo->id, $photo->variantSourceHash());
        try {
            $job->handle($factory, new PhotoVariantGenerator);
            $this->fail('Expected Drive error to reach queue retry handling');
        } catch (\RuntimeException $exception) {
            $this->assertSame(429, $exception->getCode());
        }
        $this->assertGreaterThan(1, $job->tries);
        $this->assertGreaterThan($job->backoff[0], $job->backoff[1]);
        $this->assertEmpty(Storage::disk('photos')->allFiles());
        $this->assertNull($photo->fresh()->variants_source_hash);
    }

    public function test_admin_thumbnail_is_owner_only_and_photo_cannot_cross_galleries(): void
    {
        $photo = $this->photo();
        $other = $this->photo();
        $this->get(route('gallery.photo.thumbnail', [$other->gallery->slug, $photo->id]))->assertNotFound();
        $this->actingAs($other->gallery->photographer)
            ->get(route('gallery.photo.admin-thumbnail', $photo))->assertForbidden();
        $photo->gallery->update(['is_published' => false]);
        $this->actingAs($photo->gallery->photographer)
            ->get(route('gallery.photo.admin-thumbnail', $photo))->assertOk();
    }

    public function test_gallery_deletion_cleans_cascaded_photo_files(): void
    {
        $photo = $this->photo();
        (new GeneratePhotoVariants($photo->id, $photo->variantSourceHash()))
            ->handle($this->downloadFactory($this->jpeg(100, 75)), new PhotoVariantGenerator);
        $photo->gallery->delete();
        $this->assertDatabaseMissing('photos', ['id' => $photo->id]);
        (new DeletePhotoFiles($photo->gallery_id, $photo->id))->handle();
        $this->assertEmpty(Storage::disk('photos')->allFiles());
    }

    public function test_public_and_link_downloads_redirect_to_drive_without_downloading_bytes(): void
    {
        $photo = $this->photo();
        $downloadUrl = 'https://drive.google.com/download/example';

        $provider = Mockery::mock(GoogleDriveProvider::class);
        $provider->shouldNotReceive('download');
        $provider->shouldReceive('browserDownloadUrl')
            ->twice()
            ->with('file-1')
            ->andReturn($downloadUrl);

        $factory = Mockery::mock(GoogleDriveProviderFactory::class);
        $factory->shouldReceive('make')->twice()->andReturn($provider);
        $this->app->instance(GoogleDriveProviderFactory::class, $factory);

        foreach ([Gallery::ACCESS_PUBLIC, Gallery::ACCESS_LINK] as $accessType) {
            $photo->gallery->update(['access_type' => $accessType]);

            $this->get(route('gallery.photo.download', [$photo->gallery->slug, $photo->id]))
                ->assertRedirect($downloadUrl);
        }
    }

    private function photo(): Photo
    {
        $gallery = Gallery::factory()->for(User::factory())->published()->public()->create();

        return Photo::create([
            'gallery_id' => $gallery->id, 'drive_file_id' => 'file-1',
            'filename' => 'photo.jpg', 'mime_type' => 'image/jpeg',
        ]);
    }

    private function downloadFactory(mixed $bytes): GoogleDriveProviderFactory
    {
        $provider = Mockery::mock(GoogleDriveProvider::class);
        $provider->shouldReceive('download')->once()->with('file-1')->andReturn($bytes);
        $factory = Mockery::mock(GoogleDriveProviderFactory::class);
        $factory->shouldReceive('make')->once()->andReturn($provider);

        return $factory;
    }

    private function jpeg(int $width, int $height): string
    {
        $image = imagecreatetruecolor($width, $height);
        ob_start();
        imagejpeg($image);
        $bytes = ob_get_clean();
        imagedestroy($image);

        return $bytes;
    }

    private function jpegWithExif(): string
    {
        $make = "Canon\0";
        $model = "EOS R5\0";
        $date = "2026:10:04 10:30:00\0";
        $lens = "RF 50mm F1.8 STM\0";
        $ifd0Offset = 8;
        $ifd0Length = 2 + 3 * 12 + 4;
        $makeOffset = $ifd0Offset + $ifd0Length;
        $modelOffset = $makeOffset + strlen($make);
        $exifOffset = $modelOffset + strlen($model);
        $exifLength = 2 + 6 * 12 + 4;
        $fNumberOffset = $exifOffset + $exifLength;
        $exposureOffset = $fNumberOffset + 8;
        $focalLengthOffset = $exposureOffset + 8;
        $dateOffset = $focalLengthOffset + 8;
        $lensOffset = $dateOffset + strlen($date);

        $entry = static fn (int $tag, int $type, int $count, string $value): string =>
            pack('v2V', $tag, $type, $count) . str_pad($value, 4, "\0");
        $offsetEntry = static fn (int $tag, int $type, int $count, int $offset): string =>
            $entry($tag, $type, $count, pack('V', $offset));

        $ifd0 = pack('v', 3)
            . $offsetEntry(0x010F, 2, strlen($make), $makeOffset)
            . $offsetEntry(0x0110, 2, strlen($model), $modelOffset)
            . $offsetEntry(0x8769, 4, 1, $exifOffset)
            . pack('V', 0)
            . $make
            . $model;

        $exif = pack('v', 6)
            . $entry(0x829A, 5, 1, pack('V', $exposureOffset))
            . $entry(0x829D, 5, 1, pack('V', $fNumberOffset))
            . $offsetEntry(0x9003, 2, strlen($date), $dateOffset)
            . $entry(0x8827, 3, 1, pack('v', 200))
            . $entry(0x920A, 5, 1, pack('V', $focalLengthOffset))
            . $offsetEntry(0xA434, 2, strlen($lens), $lensOffset)
            . pack('V', 0)
            . pack('V2', 28, 10)
            . pack('V2', 1, 125)
            . pack('V2', 50, 1)
            . $date
            . $lens;

        $tiff = 'II' . pack('vV', 42, $ifd0Offset) . $ifd0 . $exif;
        $payload = "Exif\0\0" . $tiff;
        $app1 = "\xFF\xE1" . pack('n', strlen($payload) + 2) . $payload;
        $jpeg = $this->jpeg(100, 75);

        return substr($jpeg, 0, 2) . $app1 . substr($jpeg, 2);
    }
}

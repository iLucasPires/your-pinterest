<?php

namespace Tests\Feature\Gallery;

use App\Actions\Gallery\SyncGalleryFromDrive;
use App\DTOs\DriveFileDTO;
use App\Jobs\GeneratePhotoVariants;
use App\Jobs\SyncGalleryJob;
use App\Models\Gallery\Gallery;
use App\Models\Gallery\Photo;
use App\Models\User;
use App\Services\Google\GoogleDriveProvider;
use App\Services\Google\GoogleDriveProviderFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class SyncGalleryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
    }

    public function test_sync_creates_photos_from_drive(): void
    {
        $user = User::factory()->create();
        $gallery = Gallery::factory()
            ->for($user)
            ->withFolder('drive-folder-id')
            ->create();

        $driveFile = new DriveFileDTO(
            id: 'file-1',
            name: 'IMG_0001.jpg',
            mimeType: 'image/jpeg',
            size: 1024000,
            width: 3000,
            height: 2000,
            thumbnailUrl: 'https://drive.google.com/thumb/file-1',
            modifiedAt: new \DateTimeImmutable('2026-01-01'),
        );

        $this->mockDriveProvider($user, [$driveFile]);

        $result = app(SyncGalleryFromDrive::class)->handle($gallery);

        $this->assertEquals(1, $result->added);
        $this->assertEquals(0, $result->removed);

        $this->assertDatabaseHas('photos', [
            'gallery_id' => $gallery->id,
            'drive_file_id' => 'file-1',
            'filename' => 'IMG_0001.jpg',
            'width' => 3000,
            'height' => 2000,
        ]);

        Queue::assertPushed(GeneratePhotoVariants::class, 1);
    }

    public function test_sync_removes_photos_deleted_from_drive(): void
    {
        $user = User::factory()->create();
        $gallery = Gallery::factory()
            ->for($user)
            ->withFolder('drive-folder-id')
            ->create();

        // A photo that exists in DB but not in Drive anymore
        Photo::create([
            'gallery_id' => $gallery->id,
            'drive_file_id' => 'deleted-file',
            'filename' => 'deleted.jpg',
            'sort_order' => 0,
        ]);

        $this->mockDriveProvider($user, []); // Drive returns no files

        $result = app(SyncGalleryFromDrive::class)->handle($gallery);

        $this->assertEquals(0, $result->added);
        $this->assertEquals(1, $result->removed);

        $this->assertDatabaseMissing('photos', ['drive_file_id' => 'deleted-file']);
    }

    public function test_sync_job_saves_drive_exif_for_existing_photos(): void
    {
        $user = User::factory()->create();
        $gallery = Gallery::factory()->for($user)->withFolder('folder')->create();
        $photo = Photo::create([
            'gallery_id' => $gallery->id,
            'drive_file_id' => 'file-1',
            'filename' => 'photo.jpg',
            'exif_metadata' => ['lens' => 'Existing lens'],
        ]);
        $file = new \Google\Service\Drive\DriveFile([
            'id' => 'file-1',
            'name' => 'photo.jpg',
            'mimeType' => 'image/jpeg',
            'imageMediaMetadata' => [
                'cameraMake' => 'Canon',
                'cameraModel' => 'EOS R5',
                'isoSpeed' => 200,
                'aperture' => 2.8,
                'exposureTime' => 0.008,
                'focalLength' => 50.0,
                'time' => '2026:10:04 10:30:00',
            ],
        ]);
        $this->mockDriveProvider($user, [DriveFileDTO::fromGoogleFile($file)]);

        (new SyncGalleryJob($gallery->id))->handle(app(SyncGalleryFromDrive::class));

        $this->assertEquals([
            'camera_make' => 'Canon',
            'camera_model' => 'EOS R5',
            'lens' => 'Existing lens',
            'iso' => 200,
            'aperture' => 2.8,
            'shutter_speed' => '0.008',
            'focal_length_mm' => 50.0,
            'captured_at' => '2026:10:04 10:30:00',
        ], $photo->fresh()->exif_metadata);

        $file->setImageMediaMetadata(new \Google\Service\Drive\DriveFileImageMediaMetadata);
        $this->mockDriveProvider($user, [DriveFileDTO::fromGoogleFile($file)]);
        (new SyncGalleryJob($gallery->id))->handle(app(SyncGalleryFromDrive::class));

        $this->assertSame('Canon', $photo->fresh()->exif_metadata['camera_make']);
    }

    public function test_sync_updates_metadata_for_existing_photos(): void
    {
        $user = User::factory()->create();
        $gallery = Gallery::factory()
            ->for($user)
            ->withFolder('drive-folder-id')
            ->create();

        Photo::create([
            'gallery_id' => $gallery->id,
            'drive_file_id' => 'file-1',
            'filename' => 'old-name.jpg',
            'sort_order' => 0,
        ]);

        $updated = new DriveFileDTO(
            id: 'file-1',
            name: 'new-name.jpg',
            mimeType: 'image/jpeg',
            size: 2000,
            width: 1920,
            height: 1080,
            thumbnailUrl: null,
            modifiedAt: null,
        );

        $this->mockDriveProvider($user, [$updated]);

        $result = app(SyncGalleryFromDrive::class)->handle($gallery);

        $this->assertEquals(0, $result->added);
        $this->assertEquals(1, $result->updated);

        $this->assertDatabaseHas('photos', [
            'drive_file_id' => 'file-1',
            'filename' => 'new-name.jpg',
        ]);
    }

    public function test_sync_updates_last_synced_at(): void
    {
        $user = User::factory()->create();
        $gallery = Gallery::factory()
            ->for($user)
            ->withFolder('folder-id')
            ->create();

        $this->mockDriveProvider($user, []);

        app(SyncGalleryFromDrive::class)->handle($gallery);

        $this->assertNotNull($gallery->fresh()->last_synced_at);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function mockDriveProvider(User $user, array $files): void
    {
        $provider = Mockery::mock(GoogleDriveProvider::class);
        $provider->shouldReceive('listFiles')->andReturn($files);

        $factory = Mockery::mock(GoogleDriveProviderFactory::class);
        $factory->shouldReceive('make')->with(Mockery::on(fn ($u) => $u->id === $user->id))->andReturn($provider);

        $this->app->instance(GoogleDriveProviderFactory::class, $factory);
    }
}

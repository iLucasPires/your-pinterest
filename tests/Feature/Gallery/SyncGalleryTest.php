<?php

namespace Tests\Feature\Gallery;

use App\Actions\Gallery\SyncGalleryFromDrive;
use App\DTOs\DriveFileDTO;
use App\Jobs\GeneratePhotoVariants;
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

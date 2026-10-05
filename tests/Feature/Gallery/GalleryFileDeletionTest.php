<?php

namespace Tests\Feature\Gallery;

use App\Jobs\DeleteGalleryFiles;
use App\Jobs\DeletePhotoFiles;
use App\Models\Gallery\Gallery;
use App\Models\Gallery\Photo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GalleryFileDeletionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['photos.disk' => 'photos']);
        Storage::fake('photos');
        Queue::fake();
    }

    public function test_gallery_deletion_removes_its_entire_directory_and_preserves_other_galleries(): void
    {
        $gallery = Gallery::factory()->for(User::factory())->create();
        $other = Gallery::factory()->for(User::factory())->create();
        $disk = Storage::disk('photos');
        $directory = "galleries/{$gallery->id}";
        $disk->put($directory.'/photos/999/old/preview.webp', 'orphan');
        $disk->makeDirectory($directory.'/empty');
        $otherPath = "galleries/{$other->id}/photos/1/preview.webp";
        $disk->put($otherPath, 'keep');

        $gallery->delete();

        Queue::assertPushed(DeleteGalleryFiles::class, fn ($job): bool => $job->galleryId === $gallery->id);
        $job = new DeleteGalleryFiles($gallery->id);
        $job->handle();
        $job->handle();

        $disk->assertMissing($directory);
        $disk->assertExists($otherPath);
    }

    public function test_cleanup_preserves_an_existing_gallery(): void
    {
        $gallery = Gallery::factory()->for(User::factory())->create();
        $path = "galleries/{$gallery->id}/photos/1/preview.webp";
        Storage::disk('photos')->put($path, 'keep');

        (new DeleteGalleryFiles($gallery->id))->handle();

        Storage::disk('photos')->assertExists($path);
    }

    public function test_deleted_photo_cleanup_removes_its_directory_and_preserves_sibling_photos(): void
    {
        $gallery = Gallery::factory()->for(User::factory())->create();
        $photo = Photo::create([
            'gallery_id' => $gallery->id,
            'drive_file_id' => 'file-1',
            'filename' => 'photo.jpg',
        ]);
        $directory = "galleries/{$gallery->id}/photos/{$photo->id}";
        $disk = Storage::disk('photos');
        $disk->put($directory.'/old/preview.webp', 'delete');
        $siblingPath = "galleries/{$gallery->id}/photos/999/preview.webp";
        $disk->put($siblingPath, 'keep');

        $photo->delete();
        (new DeletePhotoFiles($gallery->id, $photo->id))->handle();

        $disk->assertMissing($directory);
        $disk->assertExists($siblingPath);
    }
}

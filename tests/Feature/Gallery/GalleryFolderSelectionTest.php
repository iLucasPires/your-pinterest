<?php

namespace Tests\Feature\Gallery;

use App\DTOs\DriveFolderDTO;
use App\Filament\Resources\GalleryResource;
use App\Models\Gallery\Gallery;
use App\Models\User;
use App\Services\Google\GoogleDriveService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use ReflectionMethod;
use Tests\TestCase;

class GalleryFolderSelectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_excludes_used_folders_and_keeps_current_folder_when_editing(): void
    {
        Queue::fake();
        $owner = User::factory()->create();
        $this->actingAs($owner);

        $gallery = Gallery::factory()->for($owner)->create(['drive_folder_id' => 'current']);
        Gallery::factory()->for(User::factory())->create(['drive_folder_id' => 'used']);

        $folders = [
            new DriveFolderDTO('current', 'Current', null, 'Current'),
            new DriveFolderDTO('used', 'Used', null, 'Used'),
            new DriveFolderDTO('available', 'Available', null, 'Available'),
        ];

        $service = Mockery::mock(GoogleDriveService::class);
        $service->shouldReceive('searchFolders')->twice()->with($owner, 'album')->andReturn($folders);
        $this->app->instance(GoogleDriveService::class, $service);

        $search = new ReflectionMethod(GalleryResource::class, 'searchDriveFolders');

        $this->assertSame(['available' => 'Available'], $search->invoke(null, 'album'));
        $this->assertSame(
            ['current' => 'Current', 'available' => 'Available'],
            $search->invoke(null, 'album', $gallery),
        );
    }
}

<?php

namespace Tests\Feature\Google;

use App\Filament\Widgets\GoogleDriveFolderUsage;
use App\Filament\Widgets\GoogleDriveStorageOverview;
use App\Models\Gallery\Gallery;
use App\Models\Gallery\Photo;
use App\Models\User;
use App\Services\Google\GoogleDriveService;
use Google\Service\Drive\AboutStorageQuota;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use ReflectionMethod;
use Tests\TestCase;

class GoogleDriveChartsTest extends TestCase
{
    use RefreshDatabase;

    public function test_storage_chart_shows_drive_usage_other_services_and_available_quota(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $quota = new AboutStorageQuota;
        $quota->setLimit('10000000000');
        $quota->setUsage('5000000000');
        $quota->setUsageInDrive('3000000000');

        $service = Mockery::mock(GoogleDriveService::class);
        $service->shouldReceive('getStorageQuota')->once()->with($user)->andReturn($quota);
        $this->instance(GoogleDriveService::class, $service);

        $data = (new ReflectionMethod(GoogleDriveStorageOverview::class, 'getViewData'))
            ->invoke(new GoogleDriveStorageOverview);

        $this->assertSame('2.8 GB', $data['storage']['drive']);
        $this->assertSame('1.9 GB', $data['storage']['other']);
        $this->assertSame('4.7 GB', $data['storage']['total']);
        $this->assertSame('4.7 GB', $data['storage']['available']);
        $this->assertSame(50.0, $data['storage']['usedPercent']);
        $this->assertSame(30.0, $data['storage']['driveAllocationPercent']);
        $this->assertSame(20.0, $data['storage']['otherAllocationPercent']);
        $this->assertSame(50.0, $data['storage']['availablePercent']);
    }

    public function test_folder_chart_maps_each_drive_folder_to_its_gallery_and_synced_photos(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $weddingGallery = Gallery::factory()->for($user)->create([
            'name' => 'Wedding gallery',
            'drive_folder_id' => 'folder-a',
            'drive_folder_name' => 'Weddings',
        ]);
        $portraitGallery = Gallery::factory()->for($user)->create([
            'name' => 'Portrait gallery',
            'drive_folder_id' => 'folder-b',
            'drive_folder_name' => 'Portraits',
        ]);
        Gallery::factory()->for($user)->create(['drive_folder_id' => null]);
        Gallery::factory()->for($user)->create(['drive_folder_id' => null]);
        $otherUserGallery = Gallery::factory()->for(User::factory())->create([
            'drive_folder_id' => 'other-user-folder',
            'drive_folder_name' => 'Other user folder',
        ]);

        foreach ([$weddingGallery, $weddingGallery, $portraitGallery, $otherUserGallery] as $index => $gallery) {
            Photo::query()->create([
                'gallery_id' => $gallery->id,
                'drive_file_id' => "drive-file-{$index}",
                'filename' => "photo-{$index}.jpg",
            ]);
        }

        $data = (new ReflectionMethod(GoogleDriveFolderUsage::class, 'getViewData'))
            ->invoke(new GoogleDriveFolderUsage);

        $this->assertSame(
            ['Portrait gallery', 'Wedding gallery'],
            $data['folders']->pluck('name')->all(),
        );
        $this->assertSame(
            ['Portraits', 'Weddings'],
            $data['folders']->pluck('folderName')->all(),
        );
        $this->assertSame([1, 2], $data['folders']->pluck('photoCount')->all());
        $this->assertSame(3, $data['syncedPhotoCount']);
    }

    public function test_folder_chart_uses_gallery_name_instead_of_drive_folder_id_when_folder_name_is_missing(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Gallery::factory()->for($user)->create([
            'name' => 'Graduation',
            'drive_folder_id' => 'technical-drive-folder-id',
            'drive_folder_name' => null,
        ]);

        $data = (new ReflectionMethod(GoogleDriveFolderUsage::class, 'getViewData'))
            ->invoke(new GoogleDriveFolderUsage);

        $this->assertSame('Graduation', $data['folders'][0]['name']);
        $this->assertSame('Drive folder', $data['folders'][0]['folderName']);
        $this->assertNotSame('technical-drive-folder-id', $data['folders'][0]['folderName']);
    }
}

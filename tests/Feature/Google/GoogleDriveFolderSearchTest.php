<?php

namespace Tests\Feature\Google;

use App\DTOs\DriveFolderDTO;
use App\Models\User;
use App\Services\Google\GoogleDriveProvider;
use App\Services\Google\GoogleDriveProviderFactory;
use App\Services\Google\GoogleDriveService;
use Mockery;
use Tests\TestCase;

class GoogleDriveFolderSearchTest extends TestCase
{
    public function test_search_includes_every_descendant_of_a_matching_folder(): void
    {
        $root = new DriveFolderDTO('root-match', 'Wedding', 'drive-root', 'Events / Wedding');
        $subfolder = new DriveFolderDTO('subfolder', 'Ceremony', 'root-match');
        $nestedFolder = new DriveFolderDTO('nested', 'Portraits', 'subfolder');

        $provider = Mockery::mock(GoogleDriveProvider::class);
        $provider->shouldReceive('searchFolders')->once()->with('Wedding')->andReturn([$root]);
        $provider->shouldReceive('listFolders')->once()->with('root-match')->andReturn([$subfolder]);
        $provider->shouldReceive('listFolders')->once()->with('subfolder')->andReturn([$nestedFolder]);
        $provider->shouldReceive('listFolders')->once()->with('nested')->andReturn([]);

        $factory = Mockery::mock(GoogleDriveProviderFactory::class);
        $factory->shouldReceive('make')->once()->andReturn($provider);

        $folders = (new GoogleDriveService($factory))->searchFolders(
            new User,
            'Wedding',
            PHP_INT_MAX,
        );

        $this->assertSame(
            ['root-match', 'subfolder', 'nested'],
            array_map(fn (DriveFolderDTO $folder): string => $folder->id, $folders),
        );
        $this->assertSame('Events / Wedding / Ceremony', $folders[1]->displayName);
        $this->assertSame('Events / Wedding / Ceremony / Portraits', $folders[2]->displayName);
    }
}
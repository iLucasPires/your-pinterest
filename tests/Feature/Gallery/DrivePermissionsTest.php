<?php

namespace Tests\Feature\Gallery;

use App\Jobs\SyncGalleryDrivePermissions;
use App\Models\Client;
use App\Models\Gallery\Gallery;
use App\Models\User;
use App\Services\Google\GoogleDriveProvider;
use App\Services\Google\GoogleDriveProviderFactory;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class DrivePermissionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_change_revokes_managed_grant_and_preserves_manual_grant(): void
    {
        Queue::fake();
        $owner = User::factory()->create();
        $client = Client::factory()->for($owner)->create(['email' => 'first@example.test']);
        $gallery = Gallery::factory()->for($owner)->create([
            'access_type' => Gallery::ACCESS_PRIVATE, 'client_id' => $client->id, 'drive_folder_id' => 'folder',
        ]);
        $provider = Mockery::mock(GoogleDriveProvider::class);
        $provider->shouldNotReceive('syncPublicAccess');
        $factory = Mockery::mock(GoogleDriveProviderFactory::class);
        $factory->shouldReceive('make')->andReturn($provider);
        $provider->shouldReceive('readerPermission')->with('folder', 'first@example.test')->once()->andReturnNull();
        $provider->shouldReceive('grantReader')->with('folder', 'first@example.test')->once()->andReturn('created');
        (new SyncGalleryDrivePermissions($owner->id))->handle($factory);
        $this->assertDatabaseHas('gallery_drive_permissions', ['permission_id' => 'created', 'managed' => true]);

        $client->update(['email' => 'next@example.test']);
        $provider->shouldReceive('revokeReader')->with('folder', 'created')->once();
        $provider->shouldReceive('readerPermission')->with('folder', 'next@example.test')->once()->andReturn('manual');
        (new SyncGalleryDrivePermissions($owner->id))->handle($factory);
        $this->assertDatabaseHas('gallery_drive_permissions', ['permission_id' => 'manual', 'managed' => false]);

        $gallery->delete();
        (new SyncGalleryDrivePermissions($owner->id))->handle($factory);
        $this->assertSame(0, DB::table('gallery_drive_permissions')->count());
    }

    public function test_public_link_and_private_transitions_sync_drive_permissions(): void
    {
        Queue::fake();
        $owner = User::factory()->create();
        $client = Client::factory()->for($owner)->create(['email' => 'client@example.test']);
        $gallery = Gallery::factory()->for($owner)->published()->create([
            'access_type' => Gallery::ACCESS_PUBLIC,
            'client_id' => $client->id,
            'drive_folder_id' => 'folder',
        ]);

        $provider = Mockery::mock(GoogleDriveProvider::class);
        $factory = Mockery::mock(GoogleDriveProviderFactory::class);
        $factory->shouldReceive('make')->andReturn($provider);
        $provider->shouldReceive('syncPublicAccess')->with('folder', true)->twice()->andReturn('public-id');

        $job = new SyncGalleryDrivePermissions($owner->id);
        $job->handle($factory);
        $gallery->update(['access_type' => Gallery::ACCESS_LINK]);
        $job->handle($factory);

        $this->assertDatabaseHas('gallery_drive_permissions', [
            'email' => 'anyone', 'permission_id' => 'public-id', 'managed' => true,
        ]);

        $gallery->update(['access_type' => Gallery::ACCESS_PRIVATE]);
        $provider->shouldReceive('syncPublicAccess')->with('folder', false)->once()->andReturnNull();
        $provider->shouldReceive('readerPermission')->with('folder', $client->email)->once()->andReturnNull();
        $provider->shouldReceive('grantReader')->with('folder', $client->email)->once()->andReturn('client-id');
        $job->handle($factory);

        $this->assertDatabaseMissing('gallery_drive_permissions', ['email' => 'anyone']);
        $this->assertDatabaseHas('gallery_drive_permissions', ['permission_id' => 'client-id']);
    }

    public function test_failed_public_revocation_keeps_tracking_and_does_not_grant_client_access(): void
    {
        Queue::fake();
        $owner = User::factory()->create();
        $client = Client::factory()->for($owner)->create();
        Gallery::factory()->for($owner)->create([
            'drive_folder_id' => 'folder', 'client_id' => $client->id,
            'access_type' => Gallery::ACCESS_PRIVATE,
        ]);
        DB::table('gallery_drive_permissions')->insert([
            'user_id' => $owner->id, 'folder_id' => 'folder', 'email' => 'anyone',
            'managed' => true, 'permission_id' => 'public-id',
        ]);
        $provider = Mockery::mock(GoogleDriveProvider::class);
        $provider->shouldReceive('syncPublicAccess')->with('folder', false)
            ->once()->andThrow(new RuntimeException('Inherited public access'));
        $provider->shouldNotReceive('grantReader');
        $factory = Mockery::mock(GoogleDriveProviderFactory::class);
        $factory->shouldReceive('make')->andReturn($provider);

        try {
            (new SyncGalleryDrivePermissions($owner->id))->handle($factory);
            $this->fail('Public revocation failure must propagate.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Inherited public access', $exception->getMessage());
        }

        $this->assertDatabaseHas('gallery_drive_permissions', ['permission_id' => 'public-id']);
    }

    public function test_same_drive_folder_cannot_be_assigned_to_two_galleries(): void
    {
        Queue::fake();
        $owner = User::factory()->create();
        Gallery::factory()->for($owner)->create(['drive_folder_id' => 'folder']);

        $this->expectException(QueryException::class);

        Gallery::factory()->for($owner)->create(['drive_folder_id' => 'folder']);
    }

    public function test_private_gallery_without_client_or_previous_grants_does_not_contact_drive(): void
    {
        Queue::fake();
        $owner = User::factory()->create();
        Gallery::factory()->for($owner)->create([
            'drive_folder_id' => 'folder',
            'access_type' => Gallery::ACCESS_PRIVATE,
            'client_id' => null,
        ]);
        $factory = Mockery::mock(GoogleDriveProviderFactory::class);
        $factory->shouldNotReceive('make');

        (new SyncGalleryDrivePermissions($owner->id))->handle($factory);

        $this->assertDatabaseCount('gallery_drive_permissions', 0);
    }

    public function test_unmanaged_previous_grant_is_not_revoked(): void
    {
        Queue::fake();
        $owner = User::factory()->create();
        Gallery::factory()->for($owner)->create([
            'drive_folder_id' => 'folder',
            'access_type' => Gallery::ACCESS_PRIVATE,
            'client_id' => null,
        ]);
        DB::table('gallery_drive_permissions')->insert([
            'user_id' => $owner->id,
            'folder_id' => 'folder',
            'email' => 'previous@example.test',
            'permission_id' => 'manual',
            'managed' => false,
        ]);
        $provider = Mockery::mock(GoogleDriveProvider::class);
        $provider->shouldNotReceive('syncPublicAccess');
        $provider->shouldNotReceive('readerPermission');
        $provider->shouldNotReceive('revokeReader');
        $factory = Mockery::mock(GoogleDriveProviderFactory::class);
        $factory->shouldReceive('make')->once()->andReturn($provider);

        (new SyncGalleryDrivePermissions($owner->id))->handle($factory);

        $this->assertDatabaseCount('gallery_drive_permissions', 0);
    }
}

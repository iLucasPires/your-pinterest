<?php

namespace Tests\Feature\Gallery;

use App\Models\Client;
use App\Models\Gallery\Gallery;
use App\Models\Gallery\Photo;
use App\Models\User;
use App\Services\Google\GoogleDriveProvider;
use App\Services\Google\GoogleDriveProviderFactory;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tests\TestCase;
use ZipArchive;

class GalleryPublicAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_published_public_gallery_is_accessible(): void
    {
        $user = User::factory()->create();
        $gallery = Gallery::factory()
            ->for($user)
            ->published()
            ->public()
            ->create();

        $response = $this->get(route('gallery.show', $gallery->slug));

        $response->assertOk();
        $response->assertViewIs('pages.gallery');
        $response->assertSee('content="index, follow"', false);
    }

    public function test_gallery_grid_uses_local_thumbnail_without_drive_fallback(): void
    {
        $user = User::factory()->create();
        $gallery = Gallery::factory()
            ->for($user)
            ->published()
            ->public()
            ->create();

        $photo = Photo::create([
            'gallery_id' => $gallery->id,
            'drive_file_id' => 'file-1',
            'filename' => 'photo.jpg',
            'thumbnail_url' => 'https://drive.google.com/thumb/file-1',
            'sort_order' => 0,
        ]);

        $response = $this->get(route('gallery.show', $gallery->slug));

        $response->assertOk();
        $response->assertSee('src="'.route('gallery.photo.thumbnail', [$gallery->slug, $photo->id]).'"', false);
        $response->assertDontSee('https://drive.google.com/thumb/file-1', false);
        $response->assertSee(route('gallery.download', $gallery->slug), false);
    }

    public function test_published_gallery_downloads_original_photos_as_a_zip(): void
    {
        $photographer = User::factory()->create();
        $gallery = Gallery::factory()
            ->for($photographer)
            ->published()
            ->public()
            ->create();
        Photo::create([
            'gallery_id' => $gallery->id,
            'drive_file_id' => 'drive-file-one',
            'filename' => 'photo-one.jpg',
            'size' => strlen('original photo one'),
            'sort_order' => 0,
        ]);
        Photo::create([
            'gallery_id' => $gallery->id,
            'drive_file_id' => 'drive-file-two',
            'filename' => 'photo-two.jpg',
            'size' => strlen('original photo two'),
            'sort_order' => 1,
        ]);

        $firstPhotoStream = Utils::streamFor('original photo one');
        $firstPhotoStream->seek(0, SEEK_END);

        $provider = \Mockery::mock(GoogleDriveProvider::class);
        $provider->shouldReceive('download')
            ->once()
            ->with('drive-file-one')
            ->andReturn($firstPhotoStream);
        $provider->shouldReceive('download')
            ->once()
            ->with('drive-file-two')
            ->andReturn(Utils::streamFor('original photo two'));

        $factory = \Mockery::mock(GoogleDriveProviderFactory::class);
        $factory->shouldReceive('make')->once()->andReturn($provider);
        $this->app->instance(GoogleDriveProviderFactory::class, $factory);

        $response = $this->get(route('gallery.download', $gallery->slug));

        $response->assertDownload($gallery->slug.'.zip');

        $binaryResponse = $response->baseResponse;
        $this->assertInstanceOf(BinaryFileResponse::class, $binaryResponse);

        $archivePath = $binaryResponse->getFile()->getPathname();
        $archive = new ZipArchive;

        try {
            $this->assertSame(true, $archive->open($archivePath));
            $this->assertSame('original photo one', $archive->getFromName($gallery->slug.'/photo-one.jpg'));
            $this->assertSame('original photo two', $archive->getFromName($gallery->slug.'/photo-two.jpg'));
        } finally {
            $archive->close();
            unlink($archivePath);
        }
    }

    public function test_private_gallery_download_is_hidden_and_forbidden_without_access(): void
    {
        $photographer = User::factory()->create();
        $gallery = Gallery::factory()
            ->for($photographer)
            ->published()
            ->protected()
            ->create();
        Photo::create([
            'gallery_id' => $gallery->id,
            'drive_file_id' => 'private-drive-file',
            'filename' => 'private-photo.jpg',
            'sort_order' => 0,
        ]);

        $factory = \Mockery::mock(GoogleDriveProviderFactory::class);
        $factory->shouldNotReceive('make');
        $this->app->instance(GoogleDriveProviderFactory::class, $factory);

        $this->get(route('gallery.show', $gallery->slug))
            ->assertOk()
            ->assertDontSee(route('gallery.download', $gallery->slug), false);

        $this->get(route('gallery.download', $gallery->slug))
            ->assertForbidden();
    }

    public function test_gallery_download_rejects_a_truncated_photo_stream(): void
    {
        $photographer = User::factory()->create();
        $gallery = Gallery::factory()
            ->for($photographer)
            ->published()
            ->public()
            ->create();
        Photo::create([
            'gallery_id' => $gallery->id,
            'drive_file_id' => 'truncated-drive-file',
            'filename' => 'truncated.jpg',
            'size' => strlen('complete photo contents'),
            'sort_order' => 0,
        ]);

        $provider = \Mockery::mock(GoogleDriveProvider::class);
        $provider->shouldReceive('download')
            ->once()
            ->with('truncated-drive-file')
            ->andReturn(Utils::streamFor('truncated'));

        $factory = \Mockery::mock(GoogleDriveProviderFactory::class);
        $factory->shouldReceive('make')->once()->andReturn($provider);
        $this->app->instance(GoogleDriveProviderFactory::class, $factory);

        $this->withoutExceptionHandling();
        $this->expectException(RuntimeException::class);

        $this->get(route('gallery.download', $gallery->slug));
    }

    public function test_unpublished_gallery_returns_404(): void
    {
        $user = User::factory()->create();
        $gallery = Gallery::factory()
            ->for($user)
            ->unpublished()
            ->public()
            ->create();

        $response = $this->get(route('gallery.show', $gallery->slug));

        $response->assertNotFound();
    }

    public function test_protected_gallery_shows_access_screen_without_code(): void
    {
        $user = User::factory()->create();
        $gallery = Gallery::factory()
            ->for($user)
            ->published()
            ->protected()
            ->create();

        $response = $this->get(route('gallery.show', $gallery->slug));

        $response->assertOk();
        $response->assertViewIs('pages.gallery');
        $response->assertSee('role="dialog"', false);
        $response->assertSee('Entre com sua conta Google usando o e-mail autorizado para acessar esta galeria.');
    }

    public function test_gallery_navigation_only_shows_published_accessible_galleries_for_the_same_client(): void
    {
        $photographer = User::factory()->create();
        $client = Client::factory()->for($photographer)->create();
        $currentGallery = Gallery::factory()
            ->for($photographer)
            ->for($client)
            ->published()
            ->public()
            ->create();
        $siblingGallery = Gallery::factory()
            ->for($photographer)
            ->for($client)
            ->published()
            ->public()
            ->create(['name' => 'Accessible sibling']);
        $privateGallery = Gallery::factory()
            ->for($photographer)
            ->for($client)
            ->published()
            ->protected()
            ->create(['name' => 'Private sibling']);
        $unpublishedGallery = Gallery::factory()
            ->for($photographer)
            ->for($client)
            ->public()
            ->create(['name' => 'Unpublished sibling']);
        $otherClient = Client::factory()->for($photographer)->create();
        $otherClientGallery = Gallery::factory()
            ->for($photographer)
            ->for($otherClient)
            ->published()
            ->public()
            ->create(['name' => 'Other client gallery']);

        $response = $this->get(route('gallery.show', $currentGallery->slug));

        $response
            ->assertOk()
            ->assertSee('aria-label="Galerias deste cliente"', false)
            ->assertSee('href="'.route('gallery.show', $siblingGallery->slug).'"', false)
            ->assertSee('aria-current="page"', false)
            ->assertDontSee('Private sibling')
            ->assertDontSee('Unpublished sibling')
            ->assertDontSee('Other client gallery');
    }

    public function test_client_can_navigate_to_their_published_private_galleries(): void
    {
        $photographer = User::factory()->create();
        $clientUser = User::factory()->create();
        $client = Client::factory()->for($photographer)->create([
            'email' => $clientUser->email,
        ]);
        $currentGallery = Gallery::factory()
            ->for($photographer)
            ->for($client)
            ->published()
            ->public()
            ->create();
        $privateGallery = Gallery::factory()
            ->for($photographer)
            ->for($client)
            ->published()
            ->protected()
            ->create(['name' => 'Authorized private gallery']);

        $response = $this->actingAs($clientUser)
            ->get(route('gallery.show', $currentGallery->slug));

        $response
            ->assertOk()
            ->assertSee('href="'.route('gallery.show', $privateGallery->slug).'"', false)
            ->assertSee('Authorized private gallery');
    }
}

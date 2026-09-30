<?php

namespace Tests\Feature\Gallery;

use App\Models\Gallery\Gallery;
use App\Models\Gallery\Photo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

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
}

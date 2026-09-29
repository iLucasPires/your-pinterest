<?php

namespace Tests\Feature\Gallery;

use App\Models\Gallery\Gallery;
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
        $response->assertViewIs('pages.gallery.show');
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
        $response->assertViewIs('pages.gallery.show');
        $response->assertSee('role="dialog"', false);
        $response->assertSee('Enter the access code to view these photographs.');
    }
}
